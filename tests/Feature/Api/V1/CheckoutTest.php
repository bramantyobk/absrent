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

function checkoutPayload(Vehicle|array $vehicles, array $overrides = []): array
{
    $vehicles = $vehicles instanceof Vehicle ? [$vehicles] : $vehicles;

    return $overrides + [
        'start_at' => '2026-10-14T09:00:00+07:00',
        'days' => 2,
        'terms_accepted' => true,
        'items' => array_map(fn (Vehicle $vehicle): array => ['vehicle_id' => $vehicle->id], $vehicles),
    ];
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

it('creates one order for two vehicles on the same period and rejects a third', function () {
    $genio = Vehicle::factory()->create(['price_per_day' => 75000]);
    $avanza = Vehicle::factory()->car()->create(['price_per_day' => 350000]);
    $this->actingAs(User::factory()->create());

    $this->postJson(route('api.v1.checkout.store'), checkoutPayload([$genio, $avanza], ['days' => 3]))
        ->assertCreated()
        ->assertJsonPath('data.total_price', 3 * 75000 + 3 * 350000)
        ->assertJsonCount(2, 'data.items');

    expect(OrderItem::pluck('start_at')->unique())->toHaveCount(1)->and(OrderItem::pluck('end_at')->unique())->toHaveCount(1);

    $this->postJson(route('api.v1.checkout.store'), checkoutPayload(Vehicle::factory()->count(3)->create()->all(), ['start_at' => '2026-10-20T09:00:00+07:00']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

it('rejects the same vehicle twice in one order', function () {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.checkout.store'), checkoutPayload([$vehicle, $vehicle]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.vehicle_id');

    expect(Order::count())->toBe(0);
});

it('converts times with an offset to the company time zone and reads times without an offset as company time', function (string $startAt, string $stored) {
    config(['app.timezone' => 'Asia/Jakarta']);
    date_default_timezone_set('Asia/Jakarta');
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.checkout.store'), checkoutPayload($vehicle, ['start_at' => $startAt]))
        ->assertCreated();

    expect(OrderItem::sole()->start_at->toDateTimeString())->toBe($stored);
})->with([
    'Bali (+08:00)' => ['2026-10-14T09:00:00+08:00', '2026-10-14 08:00:00'],
    'UTC (Z)' => ['2026-10-14T02:00:00Z', '2026-10-14 09:00:00'],
    'UTC (+00:00)' => ['2026-10-14T09:00:00+00:00', '2026-10-14 16:00:00'],
    'Jakarta (+07:00)' => ['2026-10-14T09:00:00+07:00', '2026-10-14 09:00:00'],
    'no offset' => ['2026-10-14T09:00:00', '2026-10-14 09:00:00'],
]);

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
    'no items' => [['start_at' => '2026-10-14T09:00:00+07:00', 'days' => 2, 'items' => []], 'items'],
    'unknown vehicle' => [['start_at' => '2026-10-14T09:00:00+07:00', 'days' => 2, 'items' => [['vehicle_id' => 999999]]], 'items.0.vehicle_id'],
    'missing start' => [['days' => 2, 'items' => [['vehicle_id' => 1]]], 'start_at'],
    'missing days' => [['start_at' => '2026-10-14T09:00:00+07:00', 'items' => [['vehicle_id' => 1]]], 'days'],
    'zero days' => [['start_at' => '2026-10-14T09:00:00+07:00', 'days' => 0, 'items' => [['vehicle_id' => 1]]], 'days'],
]);

it('rejects rule violations and conflicts with errors keyed by field', function (array $overrides, string $field) {
    $vehicle = Vehicle::factory()->create(['max_rental_days' => 5]);
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-21 08:00');
    $before = Order::count();

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.checkout.store'), checkoutPayload($vehicle, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Order::count())->toBe($before);
})->with([
    'past start' => [['start_at' => '2026-10-11T09:00:00+07:00'], 'start_at'],
    'over the vehicle limit' => [['days' => 6], 'items.0.vehicle_id'],
    'overlapping a booking' => [['start_at' => '2026-10-19T09:00:00+07:00'], 'items.0.vehicle_id'],
    'ending inside the return buffer before a booking' => [['start_at' => '2026-10-18T05:01:00+07:00'], 'items.0.vehicle_id'],
]);

it('returns 422 when a customer already has the maximum number of pending orders', function () {
    $customer = User::factory()->create();
    Order::factory()->count(3)->for($customer)->create();

    $this->actingAs($customer)
        ->postJson(route('api.v1.checkout.store'), checkoutPayload(Vehicle::factory()->create()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

it('lists and shows only the customer own orders and returns 404 for another customer order', function () {
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
    $this->getJson(route('api.v1.orders.show', $other))->assertNotFound();
});
