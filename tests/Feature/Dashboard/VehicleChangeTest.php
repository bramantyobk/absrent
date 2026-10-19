<?php

use App\InspectionType;
use App\Models\EquipmentItem;
use App\Models\Order;
use App\Models\OrderVehicleChange;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleSchedule;
use App\OldVehicleStatus;
use App\OrderStatus;
use App\Services\ReplacementOptions;
use App\Services\VehicleFreeWindows;
use App\Services\VehicleTimeline;
use App\TransactionStatus;
use App\VehicleChangeReason;
use App\VehicleDriveType;
use App\VehicleStatus;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

/**
 * Pesanan Booking satu item (3 hari, Rp75.000/hari) pada rentang 20-23 Oktober.
 */
function bookingForChange(?Vehicle $vehicle = null): Order
{
    $vehicle ??= Vehicle::factory()->motorcycle()->create(['price_per_day' => 75000, 'odometer_km' => 1000]);
    $order = orderWithTransaction(['status' => OrderStatus::Booking, 'expires_at' => null, 'total_price' => 225000], ['status' => TransactionStatus::Paid], $vehicle);
    $order->items()->update(['start_at' => '2026-10-20 08:00:00', 'end_at' => '2026-10-23 08:00:00', 'days' => 3, 'unit_price' => 75000, 'subtotal' => 225000]);

    return $order->refresh()->load('items.vehicle');
}

/**
 * Pesanan Disewa satu item yang sedang berjalan (mulai kemarin, selesai 14 Oktober) beserta inspeksi pengambilan unit.
 */
function rentedWithPickup(?Vehicle $vehicle = null, array $equipment = []): Order
{
    $vehicle ??= Vehicle::factory()->motorcycle()->create(['price_per_day' => 75000, 'odometer_km' => 1000]);
    $order = orderWithTransaction(['status' => OrderStatus::Rented, 'expires_at' => null, 'total_price' => 225000], ['status' => TransactionStatus::Paid], $vehicle);
    $order->items()->update(['start_at' => '2026-10-11 08:00:00', 'end_at' => '2026-10-14 08:00:00', 'days' => 3, 'unit_price' => 75000, 'subtotal' => 225000]);
    VehicleInspection::factory()->create([
        'vehicle_id' => $vehicle->id, 'order_item_id' => $order->items()->first()->id, 'type' => InspectionType::Pickup,
        'odometer_km' => 1000, 'energy_level' => 100, 'equipment_checks' => $equipment,
    ]);

    return $order->refresh()->load('items.vehicle');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function changePayload(Vehicle $replacement, array $overrides = []): array
{
    return $overrides + ['vehicle_id' => $replacement->id, 'reason' => 'vehicle_damaged', 'final_amount' => 0, 'note' => 'Ban bocor.'];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rentedChangePayload(Vehicle $replacement, array $overrides = []): array
{
    return changePayload($replacement, $overrides + [
        'old_vehicle' => ['status' => 'returned', 'inspection' => ['odometer_km' => 1100, 'energy_level' => 40]],
        'pickup' => ['odometer_km' => 5000, 'energy_level' => 100],
    ]);
}

function changeUrl(Order $order): string
{
    return route('dashboard.orders.items.vehicle-change.store', [$order, $order->items->first()]);
}

it('rejects guests and customers on the vehicle change routes', function () {
    $order = bookingForChange();
    $replacement = Vehicle::factory()->motorcycle()->create();

    $this->post(changeUrl($order), changePayload($replacement))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post(changeUrl($order), changePayload($replacement))->assertForbidden();
});

it('changes the vehicle on a booking without inspections, keeping the period and item prices', function () {
    $operator = User::factory()->operator()->create();
    $order = bookingForChange();
    $item = $order->items->first();
    $old = $item->vehicle;
    $new = Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'model' => 'NMAX', 'price_per_day' => 100000]);

    $this->actingAs($operator)
        ->post(changeUrl($order), changePayload($new, ['reason' => 'customer_request', 'final_amount' => 50000, 'note' => 'Minta unit lebih besar.']))
        ->assertRedirect(route('dashboard.orders.show', $order))
        ->assertSessionHasNoErrors();

    $change = OrderVehicleChange::sole();
    $item->refresh();

    expect($item->vehicle_id)->toBe($new->id)
        ->and($item->unit_price)->toBe(75000)
        ->and($item->subtotal)->toBe(225000)
        ->and($item->start_at->toDateTimeString())->toBe('2026-10-20 08:00:00')
        ->and($change->from_vehicle_id)->toBe($old->id)
        ->and($change->to_vehicle_id)->toBe($new->id)
        ->and($change->reason)->toBe(VehicleChangeReason::CustomerRequest)
        ->and($change->from_order_status)->toBe(OrderStatus::Booking)
        ->and($change->old_unit_price)->toBe(75000)
        ->and($change->new_unit_price)->toBe(100000)
        ->and($change->suggested_amount)->toBe(75000)
        ->and($change->final_amount)->toBe(50000)
        ->and($change->old_vehicle_status)->toBeNull()
        ->and($change->changed_by)->toBe($operator->id)
        ->and($order->refresh()->total_price)->toBe(275000)
        ->and($order->status)->toBe(OrderStatus::Booking)
        ->and($order->transaction->amount)->toBe(225000)
        ->and(VehicleInspection::count())->toBe(0);

    $isFree = fn (Vehicle $vehicle): bool => Vehicle::query()->availableBetween(now()->setDate(2026, 10, 20)->setTime(8, 0), now()->setDate(2026, 10, 23)->setTime(8, 0))->whereKey($vehicle->id)->exists();

    expect($isFree($old))->toBeTrue()->and($isFree($new))->toBeFalse();
});

it('suggests days times the daily rate difference, positive for extra payment and negative for refund', function () {
    $order = bookingForChange();
    $item = $order->items->first();
    $cheaper = Vehicle::factory()->motorcycle()->create(['price_per_day' => 50000]);
    $this->actingAs(User::factory()->operator()->create());

    $this->post(changeUrl($order), changePayload($cheaper, ['final_amount' => -75000]));

    expect(OrderVehicleChange::sole()->suggested_amount)->toBe(-75000)
        ->and($order->refresh()->total_price)->toBe(150000)
        ->and(app(ReplacementOptions::class)->suggestedAmount($item->refresh(), Vehicle::factory()->make(['price_per_day' => 80000])))->toBe(15000);
});

it('allows a cross-category swap only for customer requests', function () {
    $order = bookingForChange();
    $car = Vehicle::factory()->car()->create(['price_per_day' => 300000]);
    $this->actingAs(User::factory()->operator()->create());

    foreach (['accident', 'vehicle_damaged', 'vehicle_late_return', 'other'] as $reason) {
        $this->post(changeUrl($order), changePayload($car, ['reason' => $reason]))->assertSessionHasErrors('vehicle_id');
    }

    expect(OrderVehicleChange::count())->toBe(0);

    $this->post(changeUrl($order), changePayload($car, ['reason' => 'customer_request', 'final_amount' => 675000]))->assertSessionHasNoErrors();

    expect($order->items()->first()->vehicle_id)->toBe($car->id)->and($order->refresh()->total_price)->toBe(900000);
});

it('rejects an unavailable, hidden, identical, or serviced replacement', function (Closure $makeReplacement) {
    $order = bookingForChange();
    $replacement = $makeReplacement($order);

    $this->actingAs(User::factory()->operator()->create())
        ->post(changeUrl($order), changePayload($replacement))
        ->assertSessionHasErrors('vehicle_id');

    expect($order->items()->first()->vehicle_id)->toBe($order->items->first()->vehicle_id)->and(OrderVehicleChange::count())->toBe(0);
})->with([
    'booked by another order' => [function (Order $order): Vehicle {
        $other = Vehicle::factory()->motorcycle()->create();
        holdVehicle($other, OrderStatus::Booking, '2026-10-22 08:00:00', '2026-10-24 08:00:00');

        return $other;
    }],
    'within the return buffer of another order' => [function (Order $order): Vehicle {
        $other = Vehicle::factory()->motorcycle()->create();
        holdVehicle($other, OrderStatus::Booking, '2026-10-18 08:00:00', '2026-10-20 06:00:00');

        return $other;
    }],
    'hidden from the catalog' => [fn (): Vehicle => Vehicle::factory()->motorcycle()->unavailable()->create()],
    'the same unit' => [fn (Order $order): Vehicle => $order->items->first()->vehicle],
    'under maintenance' => [function (Order $order): Vehicle {
        $other = Vehicle::factory()->motorcycle()->create();
        VehicleSchedule::factory()->for($other)->create(['start_at' => '2026-10-21 08:00:00', 'end_at' => '2026-10-21 18:00:00']);

        return $other;
    }],
]);

it('restricts large reductions to admins and never lets the total go below zero', function () {
    $order = bookingForChange();
    $cheaper = Vehicle::factory()->motorcycle()->create(['price_per_day' => 10000]);

    $this->actingAs(User::factory()->operator()->create())->post(changeUrl($order), changePayload($cheaper, ['final_amount' => -100000]))->assertForbidden();
    expect(OrderVehicleChange::count())->toBe(0);

    $this->post(changeUrl($order), changePayload($cheaper, ['final_amount' => -99999]))->assertSessionHasNoErrors();
    expect($order->refresh()->total_price)->toBe(125001);

    $second = Vehicle::factory()->motorcycle()->create(['price_per_day' => 5000]);
    $this->actingAs(User::factory()->admin()->create())->post(changeUrl($order), changePayload($second, ['final_amount' => -125001]))->assertSessionHasNoErrors();
    expect($order->refresh()->total_price)->toBe(0);

    $third = Vehicle::factory()->motorcycle()->create();
    $this->post(changeUrl($order), changePayload($third, ['final_amount' => -1]))->assertSessionHasErrors('final_amount');
    expect($order->refresh()->total_price)->toBe(0);
});

it('validates the reason, note, and amount', function (array $input, string $field) {
    $order = bookingForChange();

    $this->actingAs(User::factory()->operator()->create())
        ->post(changeUrl($order), $input + changePayload(Vehicle::factory()->motorcycle()->create()))
        ->assertSessionHasErrors($field);
})->with([
    'missing reason' => [['reason' => null], 'reason'],
    'unknown reason' => [['reason' => 'bored'], 'reason'],
    'other without a note' => [['reason' => 'other', 'note' => null], 'note'],
    'missing amount' => [['final_amount' => null], 'final_amount'],
    'decimal amount' => [['final_amount' => 10.5], 'final_amount'],
    'missing vehicle' => [['vehicle_id' => null], 'vehicle_id'],
    'unknown vehicle' => [['vehicle_id' => 99999], 'vehicle_id'],
]);

it('only changes vehicles on booking or rented orders and only for items of that order', function (OrderStatus $status) {
    $order = bookingForChange();
    $order->update(['status' => $status]);
    $this->actingAs(User::factory()->operator()->create());

    $this->post(changeUrl($order), changePayload(Vehicle::factory()->motorcycle()->create()))->assertSessionHasErrors('order');
})->with([OrderStatus::Pending, OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Stolen]);

it('returns 404 for an item that belongs to another order', function () {
    $order = bookingForChange();
    $other = bookingForChange();

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('dashboard.orders.items.vehicle-change.store', [$order, $other->items->first()]), changePayload(Vehicle::factory()->motorcycle()->create()))
        ->assertNotFound();
});

it('on a rented order inspects the old unit, picks up the new unit, and frees the old unit at once', function () {
    $operator = User::factory()->operator()->create();
    $helmet = EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'quantity' => 2]);
    $oldVehicle = Vehicle::factory()->motorcycle()->create(['price_per_day' => 75000, 'odometer_km' => 1000]);
    $order = rentedWithPickup($oldVehicle, [['equipment_item_id' => $helmet->id, 'name' => 'Helm SNI', 'expected' => 2, 'checked' => 2]]);
    $item = $order->items->first();
    $new = Vehicle::factory()->motorcycle()->create(['price_per_day' => 75000, 'odometer_km' => 5000]);

    $this->actingAs($operator)
        ->post(changeUrl($order), rentedChangePayload($new, [
            'old_vehicle' => ['status' => 'returned', 'inspection' => ['odometer_km' => 1100, 'energy_level' => 40, 'equipment' => [$helmet->id => 1], 'note' => 'Ban belakang bocor.']],
            'pickup' => ['odometer_km' => 5000, 'energy_level' => 100, 'equipment' => [$helmet->id => 2]],
        ]))
        ->assertSessionHasNoErrors();

    $change = OrderVehicleChange::sole();
    $return = VehicleInspection::where('vehicle_id', $oldVehicle->id)->where('type', InspectionType::Return)->sole();
    $pickup = VehicleInspection::where('vehicle_id', $new->id)->where('type', InspectionType::Pickup)->sole();

    expect($order->refresh()->status)->toBe(OrderStatus::Rented)
        ->and($item->refresh()->vehicle_id)->toBe($new->id)
        ->and($change->from_order_status)->toBe(OrderStatus::Rented)
        ->and($change->old_vehicle_status)->toBe(OldVehicleStatus::Returned)
        ->and($change->old_vehicle_inspection_id)->toBe($return->id)
        ->and($change->old_vehicle_status_by)->toBe($operator->id)
        ->and($return->order_item_id)->toBe($item->id)
        ->and($return->equipment_checks[0]['checked'])->toBe(1)
        ->and($pickup->odometer_km)->toBe(5000)
        ->and($pickup->equipment_checks[0]['checked'])->toBe(2)
        ->and($oldVehicle->refresh()->odometer_km)->toBe(1100)
        ->and($new->refresh()->odometer_km)->toBe(5000);

    $freeNow = Vehicle::query()->availableBetween(now(), now()->addHour())->whereKey($oldVehicle->id)->exists();

    expect($freeNow)->toBeTrue();
});

it('on a rented order requires the old unit status and the new unit pickup, and rolls back on any invalid part', function () {
    $oldVehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $order = rentedWithPickup($oldVehicle);
    $new = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    $this->actingAs(User::factory()->operator()->create());

    $this->post(changeUrl($order), changePayload($new))->assertSessionHasErrors(['old_vehicle.status', 'pickup.odometer_km', 'pickup.energy_level']);
    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'pending']]))->assertSessionHasErrors('old_vehicle.note');
    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'returned']]))->assertSessionHasErrors('old_vehicle.inspection.odometer_km');
    $this->post(changeUrl($order), rentedChangePayload($new, ['pickup' => ['odometer_km' => 4999, 'energy_level' => 100]]))->assertSessionHasErrors('pickup.odometer_km');
    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'returned', 'inspection' => ['odometer_km' => 999, 'energy_level' => 40]]]))->assertSessionHasErrors('old_vehicle.inspection.odometer_km');

    expect(OrderVehicleChange::count())->toBe(0)
        ->and(VehicleInspection::count())->toBe(1)
        ->and($order->items()->first()->vehicle_id)->toBe($oldVehicle->id)
        ->and($oldVehicle->refresh()->odometer_km)->toBe(1000);
});

it('postpones the old unit inspection: the unit keeps its schedule, shows as not returned, and does not block completion', function () {
    $oldVehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $order = rentedWithPickup($oldVehicle);
    $item = $order->items->first();
    $new = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    $operator = User::factory()->operator()->create();
    $this->actingAs($operator);

    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'pending', 'note' => 'Unit masih di lokasi kecelakaan.']]))->assertSessionHasNoErrors();

    $change = OrderVehicleChange::sole();
    $stillHeld = fn (): bool => ! Vehicle::query()->availableBetween(now()->addDays(5), now()->addDays(6))->whereKey($oldVehicle->id)->exists();

    expect($change->old_vehicle_status)->toBe(OldVehicleStatus::Pending)
        ->and($change->old_vehicle_note)->toBe('Unit masih di lokasi kecelakaan.')
        ->and($change->old_vehicle_inspection_id)->toBeNull()
        ->and($stillHeld())->toBeTrue()
        ->and($oldVehicle->refresh()->displayStatus())->toBe(VehicleStatus::Overdue)
        ->and(VehicleInspection::where('vehicle_id', $oldVehicle->id)->where('type', InspectionType::Return)->exists())->toBeFalse();

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'completed', 'inspections' => [$item->id => ['odometer_km' => 5200, 'energy_level' => 50]]])->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)->and($stillHeld())->toBeTrue();

    $row = app(VehicleTimeline::class)->for($oldVehicle->refresh())->first();

    expect($row['status_label'])->toBe('Belum kembali')->and($row['title'])->toBe($order->code);
});

it('settles a postponed old unit by inspection, which frees it immediately', function () {
    $oldVehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $order = rentedWithPickup($oldVehicle);
    $new = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    $operator = User::factory()->operator()->create();
    $this->actingAs($operator);
    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'pending', 'note' => 'Unit masih di lokasi.']]));
    $change = OrderVehicleChange::sole();
    $url = route('dashboard.vehicle-changes.old-vehicle.update', $change);

    $this->patch($url, ['action' => 'returned', 'inspection' => ['odometer_km' => 900, 'energy_level' => 30]])->assertSessionHasErrors('inspection.odometer_km');
    $this->patch($url, ['action' => 'returned'])->assertSessionHasErrors('inspection.odometer_km');
    $this->patch($url, ['action' => 'returned', 'inspection' => ['odometer_km' => 1150, 'energy_level' => 30]])->assertRedirect(route('dashboard.orders.show', $order));

    $change->refresh();

    expect($change->old_vehicle_status)->toBe(OldVehicleStatus::Returned)
        ->and($change->oldVehicleInspection->odometer_km)->toBe(1150)
        ->and($change->old_vehicle_status_by)->toBe($operator->id)
        ->and($oldVehicle->refresh()->odometer_km)->toBe(1150)
        ->and(Vehicle::query()->availableBetween(now()->addDays(5), now()->addDays(6))->whereKey($oldVehicle->id)->exists())->toBeTrue();

    $this->patch($url, ['action' => 'lost', 'note' => 'Terlambat.'])->assertSessionHasErrors('action');
});

it('marks the old unit lost or stolen, hiding it from the catalog, when changing from a rented order', function () {
    $oldVehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $order = rentedWithPickup($oldVehicle);
    $new = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    $this->actingAs(User::factory()->operator()->create());

    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'lost']]))->assertSessionHasErrors('old_vehicle.note');
    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'lost', 'note' => 'Dicuri di parkiran.']]))->assertSessionHasNoErrors();

    expect(OrderVehicleChange::sole()->old_vehicle_status)->toBe(OldVehicleStatus::Lost)
        ->and($oldVehicle->refresh()->is_available)->toBeFalse()
        ->and($oldVehicle->displayStatus())->toBe(VehicleStatus::Hidden);

    expect(app(VehicleTimeline::class)->for($oldVehicle)->first()['status_label'])->toBe('Hilang/dicuri');
});

it('marks a postponed old unit as lost later, with a required note', function () {
    $oldVehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $order = rentedWithPickup($oldVehicle);
    $new = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    $this->actingAs(User::factory()->operator()->create());
    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'pending', 'note' => 'Menunggu unit.']]));
    $url = route('dashboard.vehicle-changes.old-vehicle.update', OrderVehicleChange::sole());

    $this->patch($url, ['action' => 'lost'])->assertSessionHasErrors('note');
    $this->patch($url, ['action' => 'lost', 'note' => 'Tidak ditemukan.'])->assertSessionHasNoErrors();

    expect(OrderVehicleChange::sole()->old_vehicle_status)->toBe(OldVehicleStatus::Lost)->and($oldVehicle->refresh()->is_available)->toBeFalse();
});

it('only checks the replacement from now until the end of a rented order', function () {
    $order = rentedWithPickup();
    $replacement = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    holdVehicle($replacement, OrderStatus::Completed, '2026-10-10 08:00:00', '2026-10-12 08:00:00');
    holdVehicle($replacement, OrderStatus::Booking, '2026-10-12 10:30:00', '2026-10-12 12:00:00');
    $this->actingAs(User::factory()->operator()->create());

    $this->post(changeUrl($order), rentedChangePayload($replacement))->assertSessionHasErrors('vehicle_id');

    $free = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    holdVehicle($free, OrderStatus::Completed, '2026-10-10 08:00:00', '2026-10-12 08:00:00');

    $this->post(changeUrl($order), rentedChangePayload($free))->assertSessionHasNoErrors();

    expect($order->items()->first()->vehicle_id)->toBe($free->id);
});

it('completes after a change using the pickup of the new unit as the odometer minimum', function () {
    $order = rentedWithPickup();
    $item = $order->items->first();
    $new = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    $this->actingAs(User::factory()->operator()->create());
    $this->post(changeUrl($order), rentedChangePayload($new, ['pickup' => ['odometer_km' => 5100, 'energy_level' => 100]]));
    $new->update(['odometer_km' => 5000]);

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'completed', 'inspections' => [$item->id => ['odometer_km' => 5050, 'energy_level' => 20]]])
        ->assertSessionHasErrors("inspections.{$item->id}.odometer_km");
    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'completed', 'inspections' => [$item->id => ['odometer_km' => 5300, 'energy_level' => 20]]])->assertSessionHasNoErrors();

    $row = app(VehicleTimeline::class)->for($new->refresh())->first();

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($row['odometer_start'])->toBe(5100)
        ->and($row['distance_km'])->toBe(200);
});

it('renders the change dialogs only for booking and rented orders and lists history and pending units', function () {
    $this->actingAs(User::factory()->operator()->create());
    $available = Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'model' => 'Aerox', 'plate_number' => 'B 7777 ZZZ']);
    $taken = Vehicle::factory()->motorcycle()->create(['brand' => 'Suzuki', 'model' => 'Satria', 'plate_number' => 'B 8888 YY']);
    holdVehicle($taken, OrderStatus::Booking, '2026-10-21 08:00:00', '2026-10-22 08:00:00');
    $booking = bookingForChange();

    $this->get(route('dashboard.orders.show', $booking))
        ->assertOk()
        ->assertSee('Ganti kendaraan')
        ->assertSee('B 7777 ZZZ')
        ->assertDontSee('B 8888 YY')
        ->assertDontSee('Unit lama:');

    $pending = orderWithTransaction(['status' => OrderStatus::Pending]);
    $this->get(route('dashboard.orders.show', $pending))->assertDontSee('Ganti kendaraan');

    $rented = rentedWithPickup();
    $replacement = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    $this->post(changeUrl($rented), rentedChangePayload($replacement, ['old_vehicle' => ['status' => 'pending', 'note' => 'Unit masih di lokasi.']]));

    $this->get(route('dashboard.orders.show', $rented))
        ->assertSee('Riwayat Ganti Kendaraan')
        ->assertSee('Ditunda')
        ->assertSee('Selesaikan unit lama')
        ->assertSee('Unit masih di lokasi.');
});

it('labels each inspection with the unit that was inspected, including the energy label across drive types', function () {
    $old = Vehicle::factory()->motorcycle()->create(['brand' => 'Honda', 'model' => 'Beat', 'drive_type' => VehicleDriveType::Automatic, 'odometer_km' => 1000]);
    $new = Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'model' => 'Gear Listrik', 'drive_type' => VehicleDriveType::Electric, 'odometer_km' => 5000]);
    $order = rentedWithPickup($old);
    $this->actingAs(User::factory()->operator()->create());
    $this->post(changeUrl($order), rentedChangePayload($new, ['reason' => 'customer_request']))->assertSessionHasNoErrors();

    $this->get(route('dashboard.orders.show', $order))
        ->assertSeeInOrder([
            'Pengambilan', 'Honda Beat', 'Bensin 100%',
            'Pengembalian', 'Honda Beat', 'Bensin 40%',
            'Pengambilan', 'Yamaha Gear Listrik', 'Baterai 100%',
        ]);
});

it('rejects maintenance on a postponed old unit with the order code, and allows it again once the unit is settled', function () {
    $old = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $order = rentedWithPickup($old);
    $new = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    $this->actingAs(User::factory()->operator()->create());
    $this->post(changeUrl($order), rentedChangePayload($new, ['old_vehicle' => ['status' => 'pending', 'note' => 'Unit masih di lokasi.']]))->assertSessionHasNoErrors();
    $maintenance = ['type' => 'maintenance', 'start_at' => '2026-10-20T09:00', 'end_at' => '2026-10-20T17:00', 'note' => 'Ganti oli'];
    $existing = VehicleSchedule::factory()->for($old)->create(['start_at' => '2026-10-25 09:00:00', 'end_at' => '2026-10-25 17:00:00']);

    $this->post(route('dashboard.vehicles.schedules.store', $old), $maintenance)
        ->assertSessionHasErrorsIn('schedule', 'start_at');
    expect(session('errors')->getBag('schedule')->first('start_at'))->toContain($order->code)->toContain('Selesaikan inspeksi');

    $this->put(route('dashboard.vehicles.schedules.update', [$old, $existing]), $maintenance)->assertSessionHasErrorsIn('schedule', 'start_at');
    expect($old->schedules()->count())->toBe(1)
        ->and(app(VehicleFreeWindows::class)->for($old->refresh(), now()))->toHaveCount(0);

    $this->patch(route('dashboard.vehicle-changes.old-vehicle.update', OrderVehicleChange::sole()), ['action' => 'returned', 'inspection' => ['odometer_km' => 1150, 'energy_level' => 30]])
        ->assertSessionHasNoErrors();

    $this->post(route('dashboard.vehicles.schedules.store', $old), $maintenance)->assertSessionHasNoErrors();

    expect($old->schedules()->count())->toBe(2)
        ->and(app(VehicleFreeWindows::class)->for($old->refresh(), now()))->not->toHaveCount(0);
});
