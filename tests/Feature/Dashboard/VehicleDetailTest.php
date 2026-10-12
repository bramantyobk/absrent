<?php

use App\InspectionType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Models\VehicleSchedule;
use App\OrderStatus;
use App\Services\VehicleFreeWindows;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

it('requires a staff role to open the vehicle detail', function () {
    $vehicle = Vehicle::factory()->create();

    $this->get(route('dashboard.vehicles.show', $vehicle))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('dashboard.vehicles.show', $vehicle))->assertForbidden();
});

it('shows vehicle information, the merged schedule history, and the equipment section', function () {
    $vehicle = Vehicle::factory()->motorcycle()->create([
        'brand' => 'Honda', 'model' => 'Genio', 'plate_number' => 'B 4321 ZZ',
        'drive_type' => 'automatic', 'energy_capacity' => 4.2, 'odometer_km' => 15000, 'description' => 'Irit dan lincah',
    ]);
    $item = holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-21 08:00');
    VehicleInspection::factory()->for($vehicle)->create(['order_item_id' => $item->id, 'type' => InspectionType::Pickup, 'odometer_km' => 15000]);
    VehicleInspection::factory()->for($vehicle)->create(['order_item_id' => $item->id, 'type' => InspectionType::Return, 'odometer_km' => 15120]);
    VehicleSchedule::factory()->for($vehicle)->between('2026-10-25 09:00', '2026-10-25 12:00')->create(['note' => 'Ganti ban']);

    $response = $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.vehicles.show', $vehicle))
        ->assertOk()
        ->assertSeeInOrder(['Honda Genio', 'B 4321 ZZ'])
        ->assertSee('Matic')
        ->assertSee('4,2 liter')
        ->assertSee('15.000 km')
        ->assertSee('Irit dan lincah')
        ->assertSee($item->order->code)
        ->assertSee('120 km')
        ->assertSee('Ganti ban')
        ->assertSee('Perlengkapan')
        ->assertSee('Jadwalkan servis');

    expect($response->viewData('timeline')->pluck('kind')->all())->toBe(['schedule', 'order']);
});

it('shows free windows between active orders and maintenance, including the buffer', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-15 08:00', '2026-10-16 08:00');
    VehicleSchedule::factory()->for($vehicle)->between('2026-10-20 08:00', '2026-10-21 08:00')->create();

    $windows = app(VehicleFreeWindows::class)->for($vehicle);

    expect($windows->map(fn (array $window) => [$window['start_at']->toDateTimeString(), $window['end_at']->toDateTimeString()])->all())->toBe([
        ['2026-10-12 10:00:00', '2026-10-15 08:00:00'],
        ['2026-10-16 11:00:00', '2026-10-20 08:00:00'],
        ['2026-10-21 08:00:00', '2026-12-11 10:00:00'],
    ]);
});

it('hides gaps shorter than the minimum window and treats a late rented order as blocking', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-12 20:00', '2026-10-13 08:00');
    VehicleSchedule::factory()->for($vehicle)->between('2026-10-13 20:00', '2026-10-14 08:00')->create();

    $windows = app(VehicleFreeWindows::class)->for($vehicle);

    expect($windows->first()['start_at']->toDateTimeString())->toBe('2026-10-14 08:00:00');

    $late = Vehicle::factory()->create();
    holdVehicle($late, OrderStatus::Rented, '2026-10-05 08:00', '2026-10-07 08:00');

    expect(app(VehicleFreeWindows::class)->for($late, Carbon::now()))->toBeEmpty();
});

it('keeps free windows only for the requested vehicle', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle(Vehicle::factory()->create(), OrderStatus::Booking, '2026-10-15 08:00', '2026-10-16 08:00');

    expect(app(VehicleFreeWindows::class)->for($vehicle))->toHaveCount(1);
});

it('records an odometer update as a manual inspection and updates the vehicle', function () {
    $operator = User::factory()->operator()->create();
    $vehicle = Vehicle::factory()->create(['odometer_km' => 10000]);

    $this->actingAs($operator)
        ->post(route('dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => '10250', 'note' => 'Dipakai operasional'])
        ->assertRedirect(route('dashboard.vehicles.show', $vehicle))
        ->assertSessionHasNoErrors();

    $inspection = $vehicle->inspections()->sole();

    expect($vehicle->refresh()->odometer_km)->toBe(10250)
        ->and($inspection->type)->toBe(InspectionType::Manual)
        ->and($inspection->odometer_km)->toBe(10250)
        ->and($inspection->note)->toBe('Dipakai operasional')
        ->and($inspection->inspected_by)->toBe($operator->id)
        ->and($inspection->order_item_id)->toBeNull();
});

it('keeps earlier odometer readings as history instead of overwriting them', function () {
    $vehicle = Vehicle::factory()->create(['odometer_km' => 100]);
    $this->actingAs(User::factory()->operator()->create());

    $this->post(route('dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => '200']);
    $this->post(route('dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => '300']);

    expect($vehicle->inspections()->orderBy('id')->pluck('odometer_km')->all())->toBe([200, 300]);
});

it('rejects an odometer lower than the last reading for an operator', function () {
    $vehicle = Vehicle::factory()->create(['odometer_km' => 10000]);

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => '9999', 'note' => 'koreksi'])
        ->assertSessionHasErrors('odometer_km', errorBag: 'odometer');

    expect($vehicle->refresh()->odometer_km)->toBe(10000)->and($vehicle->inspections()->count())->toBe(0);
});

it('lets an admin correct the odometer backwards only with a note', function () {
    $vehicle = Vehicle::factory()->create(['odometer_km' => 10000]);
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => '9000'])
        ->assertSessionHasErrors('note', errorBag: 'odometer');
    expect($vehicle->refresh()->odometer_km)->toBe(10000);

    $this->post(route('dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => '9000', 'note' => 'Salah input'])
        ->assertSessionHasNoErrors();
    expect($vehicle->refresh()->odometer_km)->toBe(9000);
});

it('accepts the first odometer reading of a vehicle without one', function () {
    $vehicle = Vehicle::factory()->create(['odometer_km' => null]);

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => '0'])
        ->assertSessionHasNoErrors();

    expect($vehicle->refresh()->odometer_km)->toBe(0);
});

it('rejects odometer updates from customers', function () {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => '10'])
        ->assertForbidden();
});
