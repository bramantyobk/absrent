<?php

use App\InspectionType;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\OrderStatus;
use App\Services\VehicleTimeline;
use App\TransactionStatus;

beforeEach(function () {
    $this->travelTo('2026-10-20 12:00:00');
});

/**
 * Pesanan Selesai dengan inspeksi pengambilan (1.000 km) dan pengembalian yang salah ketik (11.800 km), dengan odometer kendaraan
 * ikut 11.800 dan ceklis perlengkapan (helm 2, jas hujan 1) pada keduanya.
 *
 * @return array{order: Order, vehicle: Vehicle, pickup: VehicleInspection, return: VehicleInspection}
 */
function inspectedOrder(): array
{
    $vehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 11800]);
    $order = orderWithTransaction(['status' => OrderStatus::Completed, 'expires_at' => null], ['status' => TransactionStatus::Paid], $vehicle);
    $item = $order->items->first();
    $item->update(['start_at' => '2026-10-11 08:00:00', 'end_at' => '2026-10-14 08:00:00']);
    $equipment = fn (int $helmets): array => [
        ['equipment_item_id' => 1, 'name' => 'Helm SNI', 'expected' => 2, 'checked' => $helmets],
        ['equipment_item_id' => 2, 'name' => 'Jas hujan', 'expected' => 1, 'checked' => 1],
    ];

    return [
        'order' => $order,
        'vehicle' => $vehicle,
        'pickup' => VehicleInspection::factory()->create(['vehicle_id' => $vehicle->id, 'order_item_id' => $item->id, 'type' => InspectionType::Pickup, 'odometer_km' => 1000, 'energy_level' => 100, 'equipment_checks' => $equipment(2), 'inspected_at' => '2026-10-11 08:00:00']),
        'return' => VehicleInspection::factory()->create(['vehicle_id' => $vehicle->id, 'order_item_id' => $item->id, 'type' => InspectionType::Return, 'odometer_km' => 11800, 'energy_level' => 40, 'equipment_checks' => $equipment(1), 'inspected_at' => '2026-10-14 08:00:00']),
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function editPayload(array $overrides = []): array
{
    return $overrides + ['odometer_km' => 1180, 'energy_level' => 40, 'equipment' => [1 => 1, 2 => 1], 'note' => 'Koreksi odometer.'];
}

it('rejects guests, customers, and operators', function () {
    ['return' => $inspection] = inspectedOrder();
    $url = route('dashboard.inspections.update', $inspection);

    $this->patch($url, editPayload())->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->patch($url, editPayload())->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->patch($url, editPayload())->assertForbidden();

    expect($inspection->refresh()->odometer_km)->toBe(11800)->and($inspection->edited_by)->toBeNull();
});

it('lets an admin correct a typo, which updates the vehicle odometer and the distance driven', function () {
    ['order' => $order, 'vehicle' => $vehicle, 'return' => $return] = inspectedOrder();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('dashboard.inspections.update', $return), editPayload())
        ->assertRedirect(route('dashboard.orders.show', $order))
        ->assertSessionHasNoErrors();

    $return->refresh();
    $row = app(VehicleTimeline::class)->for($vehicle->refresh())->first();

    expect($return->odometer_km)->toBe(1180)
        ->and($return->note)->toBe('Koreksi odometer.')
        ->and($return->edited_by)->toBe($admin->id)
        ->and($return->edited_at->toDateTimeString())->toBe('2026-10-20 12:00:00')
        ->and($vehicle->odometer_km)->toBe(1180)
        ->and($row['distance_km'])->toBe(180)
        ->and($order->refresh()->status)->toBe(OrderStatus::Completed);
});

it('changes the vehicle odometer only when the edited inspection is the latest for that unit', function () {
    ['vehicle' => $vehicle, 'pickup' => $pickup, 'return' => $return] = inspectedOrder();
    $return->update(['odometer_km' => 1180]);
    $vehicle->update(['odometer_km' => 1180]);
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.inspections.update', $pickup), editPayload(['odometer_km' => 1100]))->assertSessionHasNoErrors();

    expect($pickup->refresh()->odometer_km)->toBe(1100)->and($vehicle->refresh()->odometer_km)->toBe(1180);
});

it('does not let an odometer jump over the neighbouring inspections of the same unit', function (int $odometer, string $inspectionKey) {
    ['vehicle' => $vehicle, 'pickup' => $pickup, 'return' => $return] = inspectedOrder();
    $return->update(['odometer_km' => 1180]);
    VehicleInspection::factory()->create(['vehicle_id' => $vehicle->id, 'type' => InspectionType::Manual, 'odometer_km' => 1100, 'inspected_at' => '2026-10-13 09:00:00']);
    $vehicle->update(['odometer_km' => 1180]);
    $target = $inspectionKey === 'pickup' ? $pickup : $return;
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.inspections.update', $target), editPayload(['odometer_km' => $odometer]))->assertSessionHasErrors('odometer_km');

    expect($target->refresh()->odometer_km)->not->toBe($odometer)->and($target->edited_by)->toBeNull();
})->with([
    'pickup above the manual inspection that follows it' => [1150, 'pickup'],
    'return below the manual inspection that precedes it' => [1050, 'return'],
]);

it('allows an odometer equal to its neighbours and ignores inspections of other units', function () {
    ['vehicle' => $vehicle, 'pickup' => $pickup, 'return' => $return] = inspectedOrder();
    $return->update(['odometer_km' => 1180]);
    VehicleInspection::factory()->create(['vehicle_id' => Vehicle::factory()->create()->id, 'type' => InspectionType::Manual, 'odometer_km' => 5, 'inspected_at' => '2026-10-12 09:00:00']);
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.inspections.update', $pickup), editPayload(['odometer_km' => 1180]))->assertSessionHasNoErrors();
    $this->patch(route('dashboard.inspections.update', $return), editPayload(['odometer_km' => 1180]))->assertSessionHasNoErrors();

    expect($pickup->refresh()->odometer_km)->toBe(1180)->and($vehicle->refresh()->odometer_km)->toBe(1180);
});

it('changes only the checked counts of the equipment checklist and keeps names and expected counts', function () {
    ['return' => $return] = inspectedOrder();
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.inspections.update', $return), editPayload(['equipment' => [1 => 2, 2 => 0]]))->assertSessionHasNoErrors();

    expect($return->refresh()->equipment_checks)->toBe([
        ['equipment_item_id' => 1, 'name' => 'Helm SNI', 'expected' => 2, 'checked' => 2],
        ['equipment_item_id' => 2, 'name' => 'Jas hujan', 'expected' => 1, 'checked' => 0],
    ]);
});

it('rejects a checklist that does not match the inspection', function (array $equipment) {
    ['return' => $return] = inspectedOrder();
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.inspections.update', $return), editPayload(['equipment' => $equipment]))->assertSessionHasErrors('equipment');

    expect($return->refresh()->edited_by)->toBeNull();
})->with([
    'missing a row' => [[1 => 2]],
    'unknown row' => [[1 => 2, 2 => 1, 3 => 1]],
    'no rows' => [[]],
]);

it('rejects manual inspections, which are corrected with Perbarui odometer', function () {
    $vehicle = Vehicle::factory()->create(['odometer_km' => 500]);
    $manual = VehicleInspection::factory()->create(['vehicle_id' => $vehicle->id, 'type' => InspectionType::Manual, 'odometer_km' => 500]);
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.inspections.update', $manual), ['odometer_km' => 400, 'energy_level' => 50, 'equipment' => []])->assertSessionHasErrors('inspection');

    expect($manual->refresh()->odometer_km)->toBe(500);
});

it('validates the odometer, energy level, counts, and note', function (array $overrides, string $field) {
    ['return' => $return] = inspectedOrder();
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('dashboard.inspections.update', $return), editPayload($overrides))->assertSessionHasErrors($field);
})->with([
    'missing odometer' => [['odometer_km' => null], 'odometer_km'],
    'decimal odometer' => [['odometer_km' => 10.5], 'odometer_km'],
    'negative odometer' => [['odometer_km' => -1], 'odometer_km'],
    'energy above 100' => [['energy_level' => 101], 'energy_level'],
    'missing energy' => [['energy_level' => null], 'energy_level'],
    'count above 99' => [['equipment' => [1 => 100, 2 => 1]], 'equipment.1'],
    'note too long' => [['note' => str_repeat('a', 1001)], 'note'],
]);

it('shows the edit button and modal to admins only, and never shows who edited', function () {
    ['order' => $order, 'return' => $return] = inspectedOrder();
    $editor = User::factory()->admin()->create(['name' => 'Admin Penyunting']);
    $this->actingAs($editor)->patch(route('dashboard.inspections.update', $return), editPayload());

    $this->actingAs(User::factory()->admin()->create(['name' => 'Admin Pembaca']))
        ->get(route('dashboard.orders.show', $order))
        ->assertOk()
        ->assertSee("editInspectionModal{$return->id}", false)
        ->assertSee('Edit inspeksi pengembalian')
        ->assertDontSee('Admin Penyunting')
        ->assertDontSee('Diedit');

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.orders.show', $order))
        ->assertOk()
        ->assertDontSee('editInspectionModal', false)
        ->assertDontSee('Edit inspeksi');
});

it('reopens the edit dialog with the entered values when the edit is rejected', function () {
    ['order' => $order, 'return' => $return] = inspectedOrder();
    $return->update(['odometer_km' => 1180]);
    $this->actingAs(User::factory()->admin()->create());

    $this->followingRedirects()
        ->from(route('dashboard.orders.show', $order))
        ->patch(route('dashboard.inspections.update', $return), editPayload(['odometer_km' => 500, 'edit_inspection_id' => $return->id, 'note' => 'Catatan baru']))
        ->assertSee('data-auto-open="editInspectionModal'.$return->id.'"', false)
        ->assertSee('Odometer tidak boleh lebih kecil dari 1.000 km')
        ->assertSee('Catatan baru');
});

it('wraps the status and vehicle change dialogs in a confirmation summary step', function () {
    $booking = orderWithTransaction(['status' => OrderStatus::Booking, 'expires_at' => null], ['status' => TransactionStatus::Paid]);
    $this->actingAs(User::factory()->operator()->create());

    $html = $this->get(route('dashboard.orders.show', $booking))->assertOk()->getContent();

    expect(substr_count($html, 'data-confirm-summary'))->toBe(2)
        ->and($html)->toContain('data-summary-title="Pesanan akan berubah menjadi Disewa."')
        ->and($html)->toContain('data-summary-label="Odometer (km)"')
        ->and($html)->toContain('data-summary-group="Penggantian"');
});
