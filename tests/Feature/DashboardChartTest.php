<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;

function orderFor(Vehicle $vehicle, OrderStatus $status = OrderStatus::Completed, int $subtotal = 100000): Order
{
    $order = Order::factory()->create(['status' => $status, 'total_price' => $subtotal]);

    OrderItem::factory()->for($order, 'order')->for($vehicle, 'vehicle')->create([
        'days' => 1,
        'unit_price' => $subtotal,
        'subtotal' => $subtotal,
    ]);

    return $order;
}

function paidOrderFor(Vehicle $vehicle, string $verifiedAt, int $subtotal, OrderStatus $status = OrderStatus::Completed): Order
{
    $order = orderFor($vehicle, $status, $subtotal);

    Transaction::factory()->paid()->for($order, 'order')->create([
        'amount' => $subtotal,
        'verified_at' => $verifiedAt,
    ]);

    return $order;
}

it('requires staff access for every chart endpoint', function (string $route) {
    $this->getJson(route($route))->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson(route($route))
        ->assertForbidden();
})->with([
    'dashboard.charts.top-vehicles',
    'dashboard.charts.order-status',
    'dashboard.charts.revenue',
]);

it('returns the top vehicles from paid orders only', function () {
    $this->actingAs(User::factory()->operator()->create());

    $genio = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $nmax = Vehicle::factory()->create(['brand' => 'Yamaha', 'model' => 'NMAX']);

    foreach (range(1, 3) as $ignored) {
        orderFor($genio, OrderStatus::Completed);
    }
    orderFor($nmax, OrderStatus::Booking);
    orderFor($nmax, OrderStatus::Pending);
    orderFor($nmax, OrderStatus::Cancelled);

    $this->getJson(route('dashboard.charts.top-vehicles'))
        ->assertOk()
        ->assertExactJson([
            'labels' => ['Honda Genio', 'Yamaha NMAX'],
            'data' => [3, 1],
            'total' => 4,
            'center_label' => 'Orderan',
        ]);
});

it('limits the top vehicles chart to five entries', function () {
    $this->actingAs(User::factory()->admin()->create());

    foreach (range(1, 7) as $number) {
        orderFor(Vehicle::factory()->create(['model' => "Model $number"]));
    }

    expect($this->getJson(route('dashboard.charts.top-vehicles'))->json('labels'))->toHaveCount(5);
});

it('counts paid orders by status', function () {
    $this->actingAs(User::factory()->admin()->create());

    $vehicle = Vehicle::factory()->create();
    orderFor($vehicle, OrderStatus::Booking);
    orderFor($vehicle, OrderStatus::Booking);
    orderFor($vehicle, OrderStatus::Rented);
    orderFor($vehicle, OrderStatus::Failed);

    $this->getJson(route('dashboard.charts.order-status'))
        ->assertOk()
        ->assertJsonPath('labels', ['Booking', 'Disewa', 'Selesai'])
        ->assertJsonPath('data', [2, 1, 0])
        ->assertJsonPath('total', 3);
});

it('sums paid revenue per day and zero-fills the range', function () {
    $this->actingAs(User::factory()->admin()->create());

    $vehicle = Vehicle::factory()->create();
    $today = now()->toDateString();
    $twoDaysAgo = now()->subDays(2)->toDateString();

    paidOrderFor($vehicle, "$today 10:00:00", 300000);
    paidOrderFor($vehicle, "$twoDaysAgo 09:00:00", 150000);

    $response = $this->getJson(route('dashboard.charts.revenue', [
        'start_date' => $twoDaysAgo,
        'end_date' => $today,
    ]))->assertOk();

    expect($response->json('data'))->toBe([150000, 0, 300000])
        ->and($response->json('total'))->toBe(450000)
        ->and($response->json('total_formatted'))->toBe('Rp450.000');
});

it('excludes unpaid, failed, and cancelled orders from revenue', function () {
    $this->actingAs(User::factory()->admin()->create());

    $vehicle = Vehicle::factory()->create();
    $now = now()->toDateTimeString();

    paidOrderFor($vehicle, $now, 100000);
    paidOrderFor($vehicle, $now, 999000, OrderStatus::Cancelled);
    orderFor($vehicle, OrderStatus::Pending, 888000);

    $this->getJson(route('dashboard.charts.revenue'))
        ->assertOk()
        ->assertJsonPath('total', 100000);
});

it('filters revenue by vehicle type', function () {
    $this->actingAs(User::factory()->admin()->create());

    $now = now()->toDateTimeString();
    paidOrderFor(Vehicle::factory()->motorcycle()->create(), $now, 70000);
    paidOrderFor(Vehicle::factory()->car()->create(), $now, 350000);

    $this->getJson(route('dashboard.charts.revenue', ['type' => 'car']))
        ->assertJsonPath('total', 350000);

    $this->getJson(route('dashboard.charts.revenue', ['type' => 'motorcycle']))
        ->assertJsonPath('total', 70000);

    $this->getJson(route('dashboard.charts.revenue'))
        ->assertJsonPath('total', 420000);
});

it('validates the revenue filters', function (array $query, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('dashboard.charts.revenue', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown type' => [['type' => 'boat'], 'type'],
    'end before start' => [['start_date' => '2026-02-10', 'end_date' => '2026-02-01'], 'end_date'],
    'range too long' => [['start_date' => '2024-01-01', 'end_date' => '2026-01-01'], 'end_date'],
]);
