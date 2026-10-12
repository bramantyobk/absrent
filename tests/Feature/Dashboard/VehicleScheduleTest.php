<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\OrderStatus;
use App\VehicleScheduleType;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function schedulePayload(array $overrides = []): array
{
    return $overrides + [
        'type' => 'maintenance',
        'start_at' => '2026-10-20T09:00',
        'end_at' => '2026-10-20T17:00',
        'note' => 'Ganti oli',
        'odometer_km' => '12500',
    ];
}

it('requires a staff role for every schedule action', function (string $method, string $route) {
    $vehicle = Vehicle::factory()->create();
    $schedule = VehicleSchedule::factory()->for($vehicle)->create();

    $this->{$method}(route($route, [$vehicle, $schedule]))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, [$vehicle, $schedule]))
        ->assertForbidden();
})->with([
    ['post', 'dashboard.vehicles.schedules.store'],
    ['put', 'dashboard.vehicles.schedules.update'],
    ['delete', 'dashboard.vehicles.schedules.destroy'],
]);

it('schedules maintenance for staff, even on a vehicle hidden from the catalog', function () {
    $operator = User::factory()->operator()->create();
    $vehicle = Vehicle::factory()->unavailable()->create();

    $this->actingAs($operator)
        ->post(route('dashboard.vehicles.schedules.store', $vehicle), schedulePayload())
        ->assertRedirect(route('dashboard.vehicles.show', $vehicle))
        ->assertSessionHasNoErrors();

    $schedule = $vehicle->schedules()->sole();

    expect($schedule->type)->toBe(VehicleScheduleType::Maintenance)
        ->and($schedule->start_at->toDateTimeString())->toBe('2026-10-20 09:00:00')
        ->and($schedule->odometer_km)->toBe(12500)
        ->and($schedule->created_by)->toBe($operator->id);
});

it('validates the schedule input', function (array $overrides, string $field) {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.vehicles.schedules.store', $vehicle), schedulePayload($overrides))
        ->assertSessionHasErrors($field, errorBag: 'schedule');

    expect(VehicleSchedule::count())->toBe(0);
})->with([
    'unknown type' => [['type' => 'lain'], 'type'],
    'end before start' => [['end_at' => '2026-10-20T08:00'], 'end_at'],
    'start in the past' => [['start_at' => '2026-10-11T09:00', 'end_at' => '2026-10-11T10:00'], 'start_at'],
    'missing start' => [['start_at' => ''], 'start_at'],
]);

it('rejects maintenance that overlaps an active order and names its code and dates', function (OrderStatus $status) {
    $vehicle = Vehicle::factory()->create();
    $item = holdVehicle($vehicle, $status, '2026-10-19 08:00', '2026-10-20 10:00');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.vehicles.schedules.store', $vehicle), schedulePayload())
        ->assertSessionHasErrors('start_at', errorBag: 'schedule');

    $message = session('errors')->getBag('schedule')->first('start_at');

    expect($message)->toContain($item->order->code)->toContain('19/10/2026 08:00')->toContain('20/10/2026 10:00')
        ->and(VehicleSchedule::count())->toBe(0);
})->with([OrderStatus::Pending, OrderStatus::Booking, OrderStatus::Rented]);

it('applies the return buffer to maintenance after an order but not before it', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, $status = OrderStatus::Booking, '2026-10-19 08:00', '2026-10-20 08:00');
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.vehicles.schedules.store', $vehicle), schedulePayload(['start_at' => '2026-10-20T10:59', 'end_at' => '2026-10-20T14:00']))
        ->assertSessionHasErrors('start_at', errorBag: 'schedule');

    $this->post(route('dashboard.vehicles.schedules.store', $vehicle), schedulePayload(['start_at' => '2026-10-20T11:00', 'end_at' => '2026-10-20T14:00']))
        ->assertSessionHasNoErrors();

    $other = Vehicle::factory()->create();
    holdVehicle($other, $status, '2026-10-22 08:00', '2026-10-23 08:00');

    $this->post(route('dashboard.vehicles.schedules.store', $other), schedulePayload(['start_at' => '2026-10-21T08:00', 'end_at' => '2026-10-22T08:00']))
        ->assertSessionHasNoErrors();
});

it('ignores cancelled orders and rejects overlap with another schedule', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Cancelled, '2026-10-20 08:00', '2026-10-21 08:00');
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.vehicles.schedules.store', $vehicle), schedulePayload())->assertSessionHasNoErrors();

    $this->post(route('dashboard.vehicles.schedules.store', $vehicle), schedulePayload(['start_at' => '2026-10-20T16:00', 'end_at' => '2026-10-20T20:00']))
        ->assertSessionHasErrors('start_at', errorBag: 'schedule');

    expect($vehicle->schedules()->count())->toBe(1);
});

it('updates a schedule that has not started', function () {
    $vehicle = Vehicle::factory()->create();
    $schedule = VehicleSchedule::factory()->for($vehicle)->between('2026-10-20 09:00', '2026-10-20 17:00')->create();

    $this->actingAs(User::factory()->operator()->create())
        ->put(route('dashboard.vehicles.schedules.update', [$vehicle, $schedule]), schedulePayload([
            'start_at' => '2026-10-21T09:00', 'end_at' => '2026-10-21T12:00', 'type' => 'other',
        ]))
        ->assertRedirect(route('dashboard.vehicles.show', $vehicle))
        ->assertSessionHasNoErrors();

    expect($schedule->refresh())
        ->type->toBe(VehicleScheduleType::Other)
        ->start_at->toDateTimeString()->toBe('2026-10-21 09:00:00');
});

it('does not conflict with itself when its own range is edited', function () {
    $vehicle = Vehicle::factory()->create();
    $schedule = VehicleSchedule::factory()->for($vehicle)->between('2026-10-20 09:00', '2026-10-20 17:00')->create();

    $this->actingAs(User::factory()->operator()->create())
        ->put(route('dashboard.vehicles.schedules.update', [$vehicle, $schedule]), schedulePayload(['end_at' => '2026-10-20T18:00']))
        ->assertSessionHasNoErrors();

    expect($schedule->refresh()->end_at->toDateTimeString())->toBe('2026-10-20 18:00:00');
});

it('lets a running schedule be shortened or extended but not moved', function () {
    $vehicle = Vehicle::factory()->create();
    $schedule = VehicleSchedule::factory()->for($vehicle)->between('2026-10-12 08:00', '2026-10-12 18:00')->create();
    $this->actingAs(User::factory()->operator()->create());
    $url = route('dashboard.vehicles.schedules.update', [$vehicle, $schedule]);

    $this->put($url, schedulePayload(['start_at' => '2026-10-12T08:00', 'end_at' => '2026-10-12T14:00']))->assertSessionHasNoErrors();
    expect($schedule->refresh()->end_at->toDateTimeString())->toBe('2026-10-12 14:00:00');

    $this->put($url, schedulePayload(['start_at' => '2026-10-12T09:00', 'end_at' => '2026-10-12T14:00']))
        ->assertSessionHasErrors('start_at', errorBag: 'schedule');

    $this->put($url, schedulePayload(['start_at' => '2026-10-12T08:00', 'end_at' => '2026-10-12T09:00']))
        ->assertSessionHasErrors('end_at', errorBag: 'schedule');

    expect($schedule->refresh()->start_at->toDateTimeString())->toBe('2026-10-12 08:00:00');
});

it('runs the conflict check when a running schedule is extended', function () {
    $vehicle = Vehicle::factory()->create();
    $schedule = VehicleSchedule::factory()->for($vehicle)->between('2026-10-12 08:00', '2026-10-12 18:00')->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-13 08:00', '2026-10-14 08:00');

    $this->actingAs(User::factory()->operator()->create())
        ->put(route('dashboard.vehicles.schedules.update', [$vehicle, $schedule]), schedulePayload(['start_at' => '2026-10-12T08:00', 'end_at' => '2026-10-13T09:00']))
        ->assertSessionHasErrors('start_at', errorBag: 'schedule');
});

it('keeps past schedules as history', function () {
    $vehicle = Vehicle::factory()->create();
    $schedule = VehicleSchedule::factory()->for($vehicle)->between('2026-10-10 08:00', '2026-10-10 18:00')->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->put(route('dashboard.vehicles.schedules.update', [$vehicle, $schedule]), schedulePayload())
        ->assertSessionHasErrors('start_at', errorBag: 'schedule');
    $this->delete(route('dashboard.vehicles.schedules.destroy', [$vehicle, $schedule]))
        ->assertSessionHasErrors('schedule', errorBag: 'schedule');

    expect($schedule->fresh())->not->toBeNull();
});

it('cancels only schedules that have not started', function () {
    $vehicle = Vehicle::factory()->create();
    $upcoming = VehicleSchedule::factory()->for($vehicle)->between('2026-10-20 09:00', '2026-10-20 17:00')->create();
    $running = VehicleSchedule::factory()->for($vehicle)->between('2026-10-12 08:00', '2026-10-12 18:00')->create();
    $this->actingAs(User::factory()->operator()->create());

    $this->delete(route('dashboard.vehicles.schedules.destroy', [$vehicle, $upcoming]))
        ->assertRedirect(route('dashboard.vehicles.show', $vehicle))
        ->assertSessionHasNoErrors();
    $this->delete(route('dashboard.vehicles.schedules.destroy', [$vehicle, $running]))
        ->assertSessionHasErrors('schedule', errorBag: 'schedule');

    expect($upcoming->fresh())->toBeNull()->and($running->fresh())->not->toBeNull();
});

it('does not reach a schedule through another vehicle', function () {
    $schedule = VehicleSchedule::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('dashboard.vehicles.schedules.destroy', [Vehicle::factory()->create(), $schedule]))
        ->assertNotFound();
});
