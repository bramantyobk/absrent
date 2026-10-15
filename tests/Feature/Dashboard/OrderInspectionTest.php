<?php

use App\InspectionType;
use App\Models\EquipmentItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\OrderStatus;
use App\Services\VehicleTimeline;
use App\TransactionStatus;
use App\VehicleDriveType;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function bookingWith(Vehicle ...$vehicles): Order
{
    $order = orderWithTransaction(['status' => OrderStatus::Booking, 'expires_at' => null], ['status' => TransactionStatus::Paid], array_shift($vehicles));

    foreach ($vehicles as $vehicle) {
        OrderItem::factory()->for($order, 'order')->for($vehicle, 'vehicle')->create();
    }

    return $order->load('items.vehicle');
}

it('saves a pickup inspection per unit, updates the odometer, and moves the order to rented in one step', function () {
    $operator = User::factory()->operator()->create();
    $motor = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $car = Vehicle::factory()->car()->create(['odometer_km' => 20000]);
    $helmet = EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'quantity' => 2, 'sort_order' => 1]);
    EquipmentItem::factory()->motorcycle()->inactive()->create(['name' => 'Jas hujan lama']);
    EquipmentItem::factory()->car()->create(['name' => 'Dongkrak', 'quantity' => 1]);
    $order = bookingWith($motor, $car);
    [$motorItem, $carItem] = $order->items->all();

    $this->actingAs($operator)
        ->patch(route('dashboard.orders.status.update', $order), [
            'status' => 'rented',
            'inspections' => [
                $motorItem->id => ['odometer_km' => 1010, 'energy_level' => 75, 'note' => 'Lecet kecil di spakbor.', 'equipment' => [$helmet->id => 2]],
                $carItem->id => ['odometer_km' => 20050, 'energy_level' => 100, 'equipment' => [EquipmentItem::where('name', 'Dongkrak')->value('id') => 1]],
            ],
        ])
        ->assertRedirect(route('dashboard.orders.show', $order))
        ->assertSessionHasNoErrors();

    $pickup = $motorItem->inspections()->sole();

    expect($order->refresh()->status)->toBe(OrderStatus::Rented)
        ->and($pickup->type)->toBe(InspectionType::Pickup)
        ->and($pickup->vehicle_id)->toBe($motor->id)
        ->and($pickup->odometer_km)->toBe(1010)
        ->and($pickup->energy_level)->toBe(75)
        ->and($pickup->note)->toBe('Lecet kecil di spakbor.')
        ->and($pickup->inspected_by)->toBe($operator->id)
        ->and($pickup->inspected_at->toDateTimeString())->toBe('2026-10-12 10:00:00')
        ->and($pickup->equipment_checks)->toBe([['equipment_item_id' => $helmet->id, 'name' => 'Helm SNI', 'expected' => 2, 'checked' => 2]])
        ->and($motor->refresh()->odometer_km)->toBe(1010)
        ->and($car->refresh()->odometer_km)->toBe(20050)
        ->and(VehicleInspection::count())->toBe(2);
});

it('returns using the pickup checklist snapshot, records the actual return time, and completes the order', function () {
    $vehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $helmet = EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'quantity' => 2]);
    $order = bookingWith($vehicle);
    $item = $order->items->first();
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$item->id => ['odometer_km' => 1000, 'energy_level' => 100, 'equipment' => [$helmet->id => 2]]]]);

    $helmet->update(['name' => 'Helm baru', 'is_active' => false]);
    $this->travel(2)->days();

    $this->patch(route('dashboard.orders.status.update', $order), [
        'status' => 'completed',
        'inspections' => [$item->id => ['odometer_km' => 1180, 'energy_level' => 40, 'equipment' => [$helmet->id => 1], 'note' => 'Satu helm hilang.']],
    ])->assertSessionHasNoErrors();

    $return = $item->inspections()->where('type', InspectionType::Return)->sole();

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($return->odometer_km)->toBe(1180)
        ->and($return->inspected_at->toDateTimeString())->toBe('2026-10-14 10:00:00')
        ->and($return->equipment_checks)->toBe([['equipment_item_id' => $helmet->id, 'name' => 'Helm SNI', 'expected' => 2, 'checked' => 1]])
        ->and($vehicle->refresh()->odometer_km)->toBe(1180);
});

it('rolls everything back when any unit inspection is invalid', function () {
    $first = Vehicle::factory()->create(['odometer_km' => 1000]);
    $second = Vehicle::factory()->create(['odometer_km' => 5000]);
    $order = bookingWith($first, $second);
    [$firstItem, $secondItem] = $order->items->all();

    $this->actingAs(User::factory()->operator()->create())
        ->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [
            $firstItem->id => ['odometer_km' => 1100, 'energy_level' => 50],
            $secondItem->id => ['odometer_km' => 4000, 'energy_level' => 50],
        ]])
        ->assertSessionHasErrors("inspections.{$secondItem->id}.odometer_km");

    expect($order->refresh()->status)->toBe(OrderStatus::Booking)
        ->and(VehicleInspection::count())->toBe(0)
        ->and($first->refresh()->odometer_km)->toBe(1000);
});

it('requires an inspection for every unit and rejects units outside the order', function () {
    $order = bookingWith(Vehicle::factory()->create(), Vehicle::factory()->create());
    $first = $order->items->first();
    $other = orderWithTransaction()->items->first();
    $this->actingAs(User::factory()->operator()->create());

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented'])->assertSessionHasErrors('inspections');
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$first->id => ['odometer_km' => 99999999, 'energy_level' => 10]]])->assertSessionHasErrors();
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$first->id => ['odometer_km' => 90000, 'energy_level' => 10]]])->assertSessionHasErrors('inspections');
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [
        $first->id => ['odometer_km' => 90000, 'energy_level' => 10],
        $other->id => ['odometer_km' => 90000, 'energy_level' => 10],
    ]])->assertSessionHasErrors('inspections');

    expect($order->refresh()->status)->toBe(OrderStatus::Booking)->and(VehicleInspection::count())->toBe(0);
});

it('rejects an odometer below the vehicle odometer or the pickup odometer', function () {
    $vehicle = Vehicle::factory()->create(['odometer_km' => 5000]);
    $order = bookingWith($vehicle);
    $item = $order->items->first();
    $this->actingAs(User::factory()->operator()->create());

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$item->id => ['odometer_km' => 4999, 'energy_level' => 50]]])
        ->assertSessionHasErrors("inspections.{$item->id}.odometer_km");

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$item->id => ['odometer_km' => 5000, 'energy_level' => 50]]])->assertSessionHasNoErrors();

    $vehicle->update(['odometer_km' => 4000]);

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'completed', 'inspections' => [$item->id => ['odometer_km' => 4990, 'energy_level' => 50]]])
        ->assertSessionHasErrors("inspections.{$item->id}.odometer_km");

    expect($order->refresh()->status)->toBe(OrderStatus::Rented);
});

it('validates energy level, odometer, and equipment counts', function (array $input, string $field) {
    $vehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 10]);
    $helmet = EquipmentItem::factory()->motorcycle()->create();
    $order = bookingWith($vehicle);
    $item = $order->items->first();

    $this->actingAs(User::factory()->operator()->create())
        ->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$item->id => $input + ['odometer_km' => 100, 'energy_level' => 50, 'equipment' => [$helmet->id => 1]]]])
        ->assertSessionHasErrors("inspections.{$item->id}.{$field}");

    expect($order->refresh()->status)->toBe(OrderStatus::Booking);
})->with([
    'energy above 100' => [['energy_level' => 101], 'energy_level'],
    'negative energy' => [['energy_level' => -1], 'energy_level'],
    'missing energy' => [['energy_level' => null], 'energy_level'],
    'decimal odometer' => [['odometer_km' => 100.5], 'odometer_km'],
    'missing odometer' => [['odometer_km' => null], 'odometer_km'],
    'equipment above 99' => [['equipment' => [1 => 100]], 'equipment.1'],
]);

it('rejects a checklist that no longer matches the active equipment', function () {
    $vehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 10]);
    EquipmentItem::factory()->motorcycle()->create();
    $order = bookingWith($vehicle);
    $item = $order->items->first();

    $this->actingAs(User::factory()->operator()->create())
        ->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$item->id => ['odometer_km' => 100, 'energy_level' => 50]]])
        ->assertSessionHasErrors("inspections.{$item->id}.equipment");

    expect($order->refresh()->status)->toBe(OrderStatus::Booking);
});

it('renders the pickup dialog with the checklist, the minimum odometer, and the energy label of the drive type', function () {
    $electric = Vehicle::factory()->motorcycle()->create(['drive_type' => VehicleDriveType::Electric, 'odometer_km' => 1234]);
    EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'quantity' => 2]);
    $order = bookingWith($electric);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.orders.show', $order))
        ->assertOk()
        ->assertSee('Inspeksi pengambilan, ubah menjadi Disewa')
        ->assertSee('Baterai (%)')
        ->assertSee('min="1234"', false)
        ->assertSee('Helm SNI')
        ->assertSee('seharusnya 2')
        ->assertDontSee('Tandai Dicuri');
});

it('prefills the return dialog from the pickup snapshot and lists the inspection history', function () {
    $vehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 100]);
    $helmet = EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'quantity' => 2]);
    $order = bookingWith($vehicle);
    $item = $order->items->first();
    $this->actingAs(User::factory()->operator()->create());
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$item->id => ['odometer_km' => 150, 'energy_level' => 90, 'equipment' => [$helmet->id => 1], 'note' => 'Helm kedua tertinggal.']]]);

    $response = $this->get(route('dashboard.orders.show', $order))
        ->assertSee('Inspeksi pengembalian, ubah menjadi Selesai')
        ->assertSee('min="150"', false)
        ->assertSee('Pengambilan')
        ->assertSee('Odometer 150 km')
        ->assertSee('Helm SNI 1/2')
        ->assertSee('Helm kedua tertinggal.');

    $pattern = '/name="inspections\\['.$item->id.'\\]\\[equipment\\]\\['.$helmet->id.'\\]"\\s+value="1"/';
    expect($response->getContent())->toMatch($pattern);
});

it('shows the distance driven on the vehicle timeline after the return', function () {
    $vehicle = Vehicle::factory()->create(['odometer_km' => 100]);
    $order = bookingWith($vehicle);
    $item = $order->items->first();
    $this->actingAs(User::factory()->operator()->create());
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented', 'inspections' => [$item->id => ['odometer_km' => 150, 'energy_level' => 90]]]);
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'completed', 'inspections' => [$item->id => ['odometer_km' => 275, 'energy_level' => 30]]]);

    $row = app(VehicleTimeline::class)->for($vehicle->refresh())->first();

    expect($row['odometer_start'])->toBe(150)->and($row['odometer_end'])->toBe(275)->and($row['distance_km'])->toBe(125);
});

it('lets only an admin mark a rented order as stolen, with a note, hiding the unit from the catalog', function () {
    $vehicle = Vehicle::factory()->create();
    $order = orderWithTransaction(['status' => OrderStatus::Rented, 'expires_at' => null], ['status' => TransactionStatus::Paid], $vehicle);

    $this->actingAs(User::factory()->operator()->create())->patch(route('dashboard.orders.status.update', $order), ['status' => 'stolen', 'note' => 'Hilang.'])->assertForbidden();
    expect($order->refresh()->status)->toBe(OrderStatus::Rented);

    $this->actingAs(User::factory()->admin()->create());
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'stolen'])->assertSessionHasErrors('note');

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'stolen', 'note' => 'Unit tidak dikembalikan, pelanggan tidak dapat dihubungi.'])->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe(OrderStatus::Stolen)
        ->and($order->status_note)->toBe('Unit tidak dikembalikan, pelanggan tidak dapat dihubungi.')
        ->and($vehicle->refresh()->is_available)->toBeFalse()
        ->and(VehicleInspection::count())->toBe(0);

    $this->get(route('vehicles.show', $vehicle))->assertNotFound();
});

it('only marks rented orders as stolen', function (OrderStatus $status) {
    $order = orderWithTransaction(['status' => $status, 'expires_at' => null], ['status' => TransactionStatus::Paid]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('dashboard.orders.status.update', $order), ['status' => 'stolen', 'note' => 'Hilang.'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe($status);
})->with([OrderStatus::Booking, OrderStatus::Completed, OrderStatus::Stolen]);

it('offers the stolen action to admins only and lists upcoming orders of the stolen unit', function () {
    $vehicle = Vehicle::factory()->create();
    $order = orderWithTransaction(['status' => OrderStatus::Rented, 'expires_at' => null, 'code' => 'ABS-STOLEN01'], ['status' => TransactionStatus::Paid], $vehicle);
    $order->items()->update(['start_at' => '2026-10-10 08:00:00', 'end_at' => '2026-10-11 08:00:00']);
    $upcoming = orderWithTransaction(['status' => OrderStatus::Booking, 'code' => 'ABS-UPCOMING1'], [], $vehicle);
    $upcoming->items()->update(['start_at' => '2026-10-20 08:00:00', 'end_at' => '2026-10-21 08:00:00']);
    $past = orderWithTransaction(['status' => OrderStatus::Completed, 'code' => 'ABS-PASTONE01'], [], $vehicle);
    $past->items()->update(['start_at' => '2026-09-01 08:00:00', 'end_at' => '2026-09-02 08:00:00']);

    $this->actingAs(User::factory()->operator()->create())->get(route('dashboard.orders.show', $order))->assertDontSee('Tandai Dicuri');

    $this->actingAs(User::factory()->admin()->create());
    $this->get(route('dashboard.orders.show', $order))->assertSee('Tandai Dicuri');
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'stolen', 'note' => 'Hilang.']);

    $this->get(route('dashboard.orders.show', $order))
        ->assertSee('Pesanan Dicuri')
        ->assertSee('ABS-UPCOMING1')
        ->assertDontSee('ABS-PASTONE01')
        ->assertDontSee('Tandai Dicuri');
});
