<?php

use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-20 12:00:00');
});

it('rejects guests and customers on the revenue endpoint', function () {
    $this->getJson(route('api.v1.dashboard.revenue.index'))->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->getJson(route('api.v1.dashboard.revenue.index'))->assertForbidden();
});

it('returns the summary, every day of the period, vehicles, and vehicle change differences', function () {
    $from = Vehicle::factory()->motorcycle()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $to = Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'model' => 'NMAX']);
    $order = revenueOrder('2026-10-18 09:00:00', 225000, OrderStatus::Booking, $from);
    $order->update(['code' => 'ABS-APIREV001']);
    revenueChange($order, '2026-10-19 10:00:00', 75000, $to);
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->getJson(route('api.v1.dashboard.revenue.index', ['start_date' => '2026-10-17', 'end_date' => '2026-10-20']))
        ->assertOk()
        ->assertJsonPath('data.summary', ['base' => 225000, 'vehicle_change_delta' => 75000, 'total' => 300000])
        ->assertJsonCount(4, 'data.days')
        ->assertJsonPath('data.days.1', ['date' => '2026-10-18', 'base' => 225000, 'vehicle_change_delta' => 0, 'total' => 225000])
        ->assertJsonPath('data.days.2.vehicle_change_delta', 75000)
        ->assertJsonPath('data.vehicles.0.name', 'Yamaha NMAX')
        ->assertJsonPath('data.vehicles.0.total', 300000)
        ->assertJsonPath('data.vehicle_changes.0.order_code', 'ABS-APIREV001')
        ->assertJsonPath('data.vehicle_changes.0.amount', 75000)
        ->assertJsonPath('data.vehicle_changes.0.to_vehicle', 'Yamaha NMAX');
});

it('filters by category and validates the period through the api', function () {
    revenueOrder('2026-10-18 09:00:00', 100000, OrderStatus::Booking, Vehicle::factory()->motorcycle()->create());
    revenueOrder('2026-10-18 10:00:00', 300000, OrderStatus::Booking, Vehicle::factory()->car()->create());
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.dashboard.revenue.index', ['type' => 'car']))->assertJsonPath('data.summary.total', 300000)->assertJsonPath('data.type', 'car');
    $this->getJson(route('api.v1.dashboard.revenue.index'))->assertJsonPath('data.summary.total', 400000);
    $this->getJson(route('api.v1.dashboard.revenue.index', ['start_date' => '2026-10-10', 'end_date' => '2026-10-01']))->assertUnprocessable()->assertJsonValidationErrors('end_date');
});
