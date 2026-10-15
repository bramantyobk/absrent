<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function checkoutPayload(Vehicle $vehicle, array $overrides = []): array
{
    return ['items' => [$overrides + ['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-14T09:00:00+00:00', 'end_at' => '2026-10-16T09:00:00+00:00']]];
}

it('rejects guests, staff, and inactive customers on the order endpoints', function (string $method, string $route, bool $needsOrder) {
    $parameters = $needsOrder ? [Order::factory()->create()] : [];

    $this->{$method}(route($route, $parameters))->assertUnauthorized();
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route, $parameters))->assertForbidden();
    $this->actingAs(User::factory()->create(['is_active' => false]))->{$method}(route($route, $parameters))->assertForbidden();
})->with([
    ['postJson', 'api.v1.checkout.store', false],
    ['getJson', 'api.v1.orders.index', false],
    ['getJson', 'api.v1.orders.show', true],
]);

it('creates a pending order with a pending transaction through the api', function () {
    $customer = User::factory()->create();
    $vehicle = Vehicle::factory()->create(['price_per_day' => 75000]);

    $this->actingAs($customer)
        ->postJson(route('api.v1.checkout.store'), checkoutPayload($vehicle))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_price', 150000)
        ->assertJsonPath('data.transaction.status', 'pending')
        ->assertJsonPath('data.transaction.amount', 150000)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.days', 2);

    expect(Order::sole()->user_id)->toBe($customer->id)->and(Transaction::count())->toBe(1);
});

it('creates one order for several vehicles with their own schedules', function () {
    $genio = Vehicle::factory()->create(['price_per_day' => 75000]);
    $avanza = Vehicle::factory()->car()->create(['price_per_day' => 350000]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.checkout.store'), ['items' => [
            ['vehicle_id' => $genio->id, 'start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-15T09:00'],
            ['vehicle_id' => $avanza->id, 'start_at' => '2026-10-20T09:00', 'end_at' => '2026-10-23T09:00'],
        ]])
        ->assertCreated()
        ->assertJsonPath('data.total_price', 75000 + 3 * 350000)
        ->assertJsonCount(2, 'data.items');
});

it('replays an idempotent request with the same order and a 200 status', function () {
    $this->actingAs(User::factory()->create());
    $vehicle = Vehicle::factory()->create();
    $headers = ['Idempotency-Key' => 'abc-123'];

    $first = $this->postJson(route('api.v1.checkout.store'), checkoutPayload($vehicle), $headers)->assertCreated();
    $second = $this->postJson(route('api.v1.checkout.store'), checkoutPayload($vehicle), $headers)->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))->and(Order::count())->toBe(1);
});

it('validates the checkout payload', function (array $payload, string $field) {
    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.checkout.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Order::count())->toBe(0);
})->with([
    'no items' => [['items' => []], 'items'],
    'unknown vehicle' => [['items' => [['vehicle_id' => 999999, 'start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-16T09:00']]], 'items.0.vehicle_id'],
    'missing dates' => [['items' => [['vehicle_id' => 1]]], 'items.0.start_at'],
]);

it('rejects rule violations and conflicts with errors keyed by item', function (array $overrides, string $field) {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-21 08:00');
    $before = Order::count();

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.checkout.store'), checkoutPayload($vehicle, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Order::count())->toBe($before);
})->with([
    'past start' => [['start_at' => '2026-10-11T09:00:00+00:00', 'end_at' => '2026-10-13T09:00:00+00:00'], 'items.0.start_at'],
    'under 24 hours' => [['end_at' => '2026-10-15T08:00:00+00:00'], 'items.0.end_at'],
    'over 7 days' => [['end_at' => '2026-10-22T09:00:00+00:00'], 'items.0.end_at'],
    'overlapping a booking' => [['start_at' => '2026-10-19T09:00:00+00:00', 'end_at' => '2026-10-21T09:00:00+00:00'], 'items.0.start_at'],
]);

it('lists and shows only the customer own orders', function () {
    $customer = User::factory()->create();
    $own = Order::factory()->for($customer)->create(['total_price' => 100000]);
    OrderItem::factory()->for($own, 'order')->for(Vehicle::factory()->create(), 'vehicle')->create();
    Transaction::factory()->for($own)->create();
    $other = Order::factory()->create();

    $this->actingAs($customer);

    $this->getJson(route('api.v1.orders.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', $own->code);

    $this->getJson(route('api.v1.orders.show', $own))->assertOk()->assertJsonPath('data.code', $own->code);
    $this->getJson(route('api.v1.orders.show', $other))->assertForbidden();
});
