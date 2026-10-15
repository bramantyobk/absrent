<?php

use App\Models\Order;
use App\Models\OrderVehicleChange;
use App\Models\User;
use App\Models\Vehicle;
use App\OldVehicleStatus;
use App\OrderStatus;
use App\TransactionStatus;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function apiBookingForChange(): Order
{
    $vehicle = Vehicle::factory()->motorcycle()->create(['price_per_day' => 75000, 'odometer_km' => 1000]);
    $order = orderWithTransaction(['status' => OrderStatus::Booking, 'expires_at' => null, 'total_price' => 225000], ['status' => TransactionStatus::Paid], $vehicle);
    $order->items()->update(['start_at' => '2026-10-20 08:00:00', 'end_at' => '2026-10-23 08:00:00', 'days' => 3, 'unit_price' => 75000, 'subtotal' => 225000]);

    return $order->refresh()->load('items.vehicle');
}

it('rejects guests and customers on the vehicle change endpoints', function (string $method, string $route) {
    $order = apiBookingForChange();
    $parameters = str_contains($route, 'old-vehicle') ? [OrderVehicleChange::factory()->create()] : [$order, $order->items->first()];

    $this->{$method}(route($route, $parameters))->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->{$method}(route($route, $parameters))->assertForbidden();
})->with([
    ['getJson', 'api.v1.dashboard.orders.items.replacement-options'],
    ['postJson', 'api.v1.dashboard.orders.items.vehicle-change'],
    ['patchJson', 'api.v1.dashboard.vehicle-changes.old-vehicle'],
]);

it('lists available replacements with suggested amounts, restricted to the same category unless a customer request', function () {
    $order = apiBookingForChange();
    $item = $order->items->first();
    $pricier = Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'price_per_day' => 100000]);
    $car = Vehicle::factory()->car()->create(['brand' => 'Toyota', 'price_per_day' => 300000]);
    $taken = Vehicle::factory()->motorcycle()->create();
    holdVehicle($taken, OrderStatus::Booking, '2026-10-21 08:00:00', '2026-10-22 08:00:00');
    Vehicle::factory()->motorcycle()->unavailable()->create();
    Sanctum::actingAs(User::factory()->operator()->create());

    $url = route('api.v1.dashboard.orders.items.replacement-options', [$order, $item]);

    $this->getJson($url.'?reason=vehicle_damaged')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pricier->id)
        ->assertJsonPath('data.0.suggested_amount', 75000);

    $this->getJson($url.'?reason=customer_request')->assertJsonCount(2, 'data')->assertJsonFragment(['id' => $car->id, 'suggested_amount' => 675000]);
    $this->getJson($url)->assertJsonCount(2, 'data');
});

it('changes the vehicle through the api and returns the change with the new order total', function () {
    $order = apiBookingForChange();
    $new = Vehicle::factory()->motorcycle()->create(['price_per_day' => 100000]);
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->postJson(route('api.v1.dashboard.orders.items.vehicle-change', [$order, $order->items->first()]), ['vehicle_id' => $new->id, 'reason' => 'vehicle_damaged', 'final_amount' => 75000])
        ->assertCreated()
        ->assertJsonPath('data.reason', 'vehicle_damaged')
        ->assertJsonPath('data.suggested_amount', 75000)
        ->assertJsonPath('data.final_amount', 75000)
        ->assertJsonPath('data.order_total_price', 300000)
        ->assertJsonPath('data.to_vehicle.id', $new->id);

    $this->getJson(route('api.v1.dashboard.orders.show', $order))
        ->assertJsonPath('data.total_price', 300000)
        ->assertJsonCount(1, 'data.vehicle_changes')
        ->assertJsonPath('data.items.0.vehicle_id', $new->id);
});

it('enforces category, authority, and validation through the api', function () {
    $order = apiBookingForChange();
    $item = $order->items->first();
    $car = Vehicle::factory()->car()->create();
    $cheap = Vehicle::factory()->motorcycle()->create(['price_per_day' => 1000]);
    $url = route('api.v1.dashboard.orders.items.vehicle-change', [$order, $item]);

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->postJson($url, ['vehicle_id' => $car->id, 'reason' => 'accident', 'final_amount' => 0])->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
    $this->postJson($url, ['vehicle_id' => $cheap->id, 'reason' => 'accident', 'final_amount' => -100000])->assertForbidden();
    $this->postJson($url, ['reason' => 'other'])->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id', 'note', 'final_amount']);

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->postJson($url, ['vehicle_id' => $cheap->id, 'reason' => 'accident', 'final_amount' => -100000])->assertCreated();
});

it('changes the vehicle on a rented order with the old unit postponed, then settles it through the api', function () {
    $oldVehicle = Vehicle::factory()->motorcycle()->create(['odometer_km' => 1000]);
    $order = rentedWithPickup($oldVehicle);
    $item = $order->items->first();
    $new = Vehicle::factory()->motorcycle()->create(['odometer_km' => 5000]);
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->postJson(route('api.v1.dashboard.orders.items.vehicle-change', [$order, $item]), [
        'vehicle_id' => $new->id, 'reason' => 'accident', 'final_amount' => 0,
        'old_vehicle' => ['status' => 'pending', 'note' => 'Unit di bengkel polisi.'],
        'pickup' => ['odometer_km' => 5000, 'energy_level' => 100],
    ])->assertCreated()->assertJsonPath('data.old_vehicle_status', 'pending');

    $change = OrderVehicleChange::sole();

    $this->patchJson(route('api.v1.dashboard.vehicle-changes.old-vehicle', $change), ['action' => 'returned'])->assertUnprocessable()->assertJsonValidationErrors('inspection.odometer_km');
    $this->patchJson(route('api.v1.dashboard.vehicle-changes.old-vehicle', $change), ['action' => 'returned', 'inspection' => ['odometer_km' => 1200, 'energy_level' => 20]])
        ->assertOk()
        ->assertJsonPath('data.old_vehicle_status', 'returned');

    expect($change->refresh()->old_vehicle_status)->toBe(OldVehicleStatus::Returned);
});
