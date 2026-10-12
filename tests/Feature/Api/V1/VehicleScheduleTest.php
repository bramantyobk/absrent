<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\OrderStatus;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function apiSchedulePayload(array $overrides = []): array
{
    return $overrides + [
        'type' => 'maintenance',
        'start_at' => '2026-10-20T09:00:00+07:00',
        'end_at' => '2026-10-20T17:00:00+07:00',
        'note' => 'Servis berkala',
    ];
}

it('rejects guests and customers on the schedule endpoints', function (string $method, string $route) {
    $vehicle = Vehicle::factory()->create();
    $schedule = VehicleSchedule::factory()->for($vehicle)->create();
    $parameters = [$vehicle, $schedule];

    $this->{$method}(route($route, $parameters))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->{$method}(route($route, $parameters))->assertForbidden();
})->with([
    ['getJson', 'api.v1.dashboard.vehicles.schedules.index'],
    ['postJson', 'api.v1.dashboard.vehicles.schedules.store'],
    ['putJson', 'api.v1.dashboard.vehicles.schedules.update'],
    ['deleteJson', 'api.v1.dashboard.vehicles.schedules.destroy'],
    ['getJson', 'api.v1.dashboard.vehicles.free-windows'],
    ['getJson', 'api.v1.dashboard.vehicles.timeline'],
    ['postJson', 'api.v1.dashboard.vehicles.odometer.store'],
]);

it('creates, lists, updates, and cancels maintenance schedules', function () {
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->operator()->create());

    $id = $this->postJson(route('api.v1.dashboard.vehicles.schedules.store', $vehicle), apiSchedulePayload())
        ->assertCreated()
        ->assertJsonPath('data.type_label', 'Servis')
        ->json('data.id');

    $this->getJson(route('api.v1.dashboard.vehicles.schedules.index', $vehicle))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->putJson(route('api.v1.dashboard.vehicles.schedules.update', [$vehicle, $id]), apiSchedulePayload(['note' => 'Ganti kampas']))
        ->assertOk()
        ->assertJsonPath('data.note', 'Ganti kampas');

    $this->deleteJson(route('api.v1.dashboard.vehicles.schedules.destroy', [$vehicle, $id]))->assertNoContent();

    expect(VehicleSchedule::count())->toBe(0);
});

it('returns 422 when maintenance overlaps an active order', function () {
    $vehicle = Vehicle::factory()->create();
    $item = holdVehicle($vehicle, OrderStatus::Pending, '2026-10-20 08:00', '2026-10-20 12:00');

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('api.v1.dashboard.vehicles.schedules.store', $vehicle), apiSchedulePayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start_at')
        ->assertJsonPath('errors.start_at.0', fn (string $message) => str_contains($message, $item->order->code));
});

it('refuses to cancel a schedule that is already running', function () {
    $vehicle = Vehicle::factory()->create();
    $schedule = VehicleSchedule::factory()->for($vehicle)->between('2026-10-12 08:00', '2026-10-12 18:00')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->deleteJson(route('api.v1.dashboard.vehicles.schedules.destroy', [$vehicle, $schedule]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('schedule');
});

it('serves free windows and the merged timeline', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-15 08:00', '2026-10-16 08:00');
    VehicleSchedule::factory()->for($vehicle)->between('2026-10-20 08:00', '2026-10-21 08:00')->create();
    $this->actingAs(User::factory()->operator()->create());

    $this->getJson(route('api.v1.dashboard.vehicles.free-windows', $vehicle))
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $this->getJson(route('api.v1.dashboard.vehicles.timeline', $vehicle))
        ->assertOk()
        ->assertJsonPath('data.0.kind', 'schedule')
        ->assertJsonPath('data.1.kind', 'order');
});

it('updates the odometer through the api and applies the same rules', function () {
    $vehicle = Vehicle::factory()->create(['odometer_km' => 500]);
    $this->actingAs(User::factory()->operator()->create());

    $this->postJson(route('api.v1.dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => 400])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('odometer_km');

    $this->postJson(route('api.v1.dashboard.vehicles.odometer.store', $vehicle), ['odometer_km' => 650])
        ->assertOk()
        ->assertJsonPath('data.odometer_km', 650);
});
