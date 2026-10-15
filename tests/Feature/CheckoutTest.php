<?php

use App\Actions\Orders\CreateOrder;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function rentalPeriod(array $overrides = []): array
{
    return $overrides + ['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-16T09:00'];
}

it('requires a customer account for checkout and orders', function (string $method, string $route) {
    $this->{$method}(route($route, [Vehicle::factory()->create()]))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route, [Vehicle::factory()->create()]))->assertForbidden();
})->with([
    ['get', 'checkout.show'],
    ['post', 'checkout.store'],
    ['post', 'checkout.items.store'],
    ['get', 'orders.index'],
]);

it('adds a vehicle with its schedule to the checkout and shows days and subtotal', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio', 'price_per_day' => 75000]);

    $this->actingAs(User::factory()->create())
        ->post(route('checkout.items.store', $vehicle), rentalPeriod())
        ->assertRedirect(route('checkout.show'))
        ->assertSessionHasNoErrors();

    $this->get(route('checkout.show'))
        ->assertOk()
        ->assertSee('Honda Genio')
        ->assertSee('2 hari')
        ->assertSee('Rp150.000')
        ->assertSee('Tambah kendaraan');
});

it('rounds the rental up to whole days', function () {
    $vehicle = Vehicle::factory()->create(['price_per_day' => 100000]);

    $this->actingAs(User::factory()->create())
        ->post(route('checkout.items.store', $vehicle), rentalPeriod(['end_at' => '2026-10-16T10:00']));

    expect($this->get(route('checkout.show'))->viewData('total'))->toBe(300000);
});

it('rejects schedules that break the rental rules', function (array $period, string $field) {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('checkout.items.store', $vehicle), $period)
        ->assertSessionHasErrors($field);

    expect(session('checkout.items', []))->toBeEmpty();
})->with([
    'start in the past' => [['start_at' => '2026-10-11T09:00', 'end_at' => '2026-10-13T09:00'], 'start_at'],
    'shorter than 24 hours' => [['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-15T08:59'], 'end_at'],
    'longer than 7 days' => [['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-21T09:01'], 'end_at'],
    'end before start' => [['start_at' => '2026-10-16T09:00', 'end_at' => '2026-10-14T09:00'], 'end_at'],
    'missing end' => [['start_at' => '2026-10-14T09:00'], 'end_at'],
]);

it('accepts exactly 24 hours and exactly 7 days', function () {
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-15T09:00'])->assertSessionHasNoErrors();
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), ['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-21T09:00'])->assertSessionHasNoErrors();
});

it('applies the per-vehicle rental limit', function () {
    $vehicle = Vehicle::factory()->create(['max_rental_days' => 3]);
    $this->actingAs(User::factory()->create());

    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-18T09:00'])
        ->assertSessionHasErrors('end_at');
    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-17T09:00'])
        ->assertSessionHasNoErrors();
});

it('rejects a range held by an active order including the return buffer, or by maintenance', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Pending, '2026-10-14 08:00', '2026-10-15 08:00');
    $this->actingAs(User::factory()->create());

    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-15T10:59', 'end_at' => '2026-10-17T10:59'])
        ->assertSessionHasErrors('start_at');
    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-15T11:00', 'end_at' => '2026-10-17T11:00'])
        ->assertSessionHasNoErrors();

    $other = Vehicle::factory()->create();
    VehicleSchedule::factory()->for($other)->between('2026-10-14 08:00', '2026-10-15 08:00')->create();

    $this->post(route('checkout.items.store', $other), rentalPeriod())->assertSessionHasErrors('start_at');
});

it('does not let a vehicle hidden from the catalog be added', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('checkout.items.store', Vehicle::factory()->unavailable()->create()), rentalPeriod())
        ->assertNotFound();
});

it('removes an item from the checkout', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', Vehicle::factory()->create(['model' => 'Satu'])), rentalPeriod());
    $this->post(route('checkout.items.store', Vehicle::factory()->create(['model' => 'Dua'])), rentalPeriod());

    $this->delete(route('checkout.items.destroy', 0))->assertRedirect(route('checkout.show'));

    $this->get(route('checkout.show'))->assertDontSee('Satu')->assertSee('Dua');
});

it('limits the number of vehicles in one order', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 10) as $number) {
        $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod())->assertSessionHasNoErrors();
    }

    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod())->assertSessionHasErrors('start_at');
});

it('creates a pending order with items and a pending transaction, then clears the checkout', function () {
    $customer = User::factory()->create();
    $genio = Vehicle::factory()->create(['price_per_day' => 75000]);
    $avanza = Vehicle::factory()->car()->create(['price_per_day' => 350000]);
    $this->actingAs($customer);
    $this->post(route('checkout.items.store', $genio), rentalPeriod());
    $this->post(route('checkout.items.store', $avanza), ['start_at' => '2026-10-20T08:00', 'end_at' => '2026-10-21T20:00']);

    $this->post(route('checkout.store'))->assertRedirect()->assertSessionHasNoErrors();

    $order = Order::query()->with(['items', 'transaction'])->sole();

    expect($order->user_id)->toBe($customer->id)
        ->and($order->code)->toMatch('/^ABS-[A-Z0-9]{8}$/')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_price)->toBe(150000 + 2 * 350000)
        ->and($order->expires_at->toDateTimeString())->toBe('2026-10-13 10:00:00')
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->firstWhere('vehicle_id', $genio->id))->toMatchArray(['days' => 2, 'unit_price' => 75000, 'subtotal' => 150000])
        ->and($order->items->firstWhere('vehicle_id', $avanza->id)->days)->toBe(2)
        ->and($order->transaction->status)->toBe(TransactionStatus::Pending)
        ->and($order->transaction->amount)->toBe($order->total_price)
        ->and(session('checkout.items', []))->toBeEmpty();
});

it('rejects an empty checkout', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('checkout.store'))
        ->assertRedirect(route('checkout.show'))
        ->assertSessionHas('error');

    expect(Order::count())->toBe(0);
});

it('rejects the order if the vehicle was taken after it was added, and keeps the checkout', function () {
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', $vehicle), rentalPeriod());

    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-14 08:00', '2026-10-15 08:00');
    $before = Order::count();

    $this->post(route('checkout.store'))->assertSessionHasErrors('items.0.start_at');

    expect(Order::count())->toBe($before)->and(session('checkout.items'))->toHaveCount(1);
    $this->get(route('checkout.show'))->assertSee('Kendaraan tidak tersedia pada rentang waktu ini.');
});

it('rejects overlapping items for the same vehicle within one order', function () {
    $vehicle = Vehicle::factory()->create();
    $customer = User::factory()->create();

    expect(fn () => app(CreateOrder::class)->handle($customer, [
        ['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-14 09:00', 'end_at' => '2026-10-16 09:00'],
        ['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-15 09:00', 'end_at' => '2026-10-17 09:00'],
    ]))->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

it('allows the same vehicle twice in one order when the ranges are apart', function () {
    $vehicle = Vehicle::factory()->create(['price_per_day' => 100000]);

    $order = app(CreateOrder::class)->handle(User::factory()->create(), [
        ['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-14 09:00', 'end_at' => '2026-10-15 09:00'],
        ['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-15 12:00', 'end_at' => '2026-10-16 12:00'],
    ]);

    expect($order->items)->toHaveCount(2)->and($order->total_price)->toBe(200000);
});

it('does not create a second order for a repeated idempotency key', function () {
    $vehicle = Vehicle::factory()->create();
    $customer = User::factory()->create();
    $items = [['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-14 09:00', 'end_at' => '2026-10-16 09:00']];

    $first = app(CreateOrder::class)->handle($customer, $items, 'kunci-1');
    $second = app(CreateOrder::class)->handle($customer, $items, 'kunci-1');
    $third = app(CreateOrder::class)->handle($customer, [
        ['vehicle_id' => Vehicle::factory()->create()->id, 'start_at' => '2026-10-14 09:00', 'end_at' => '2026-10-16 09:00'],
    ], 'kunci-2');

    expect($second->id)->toBe($first->id)
        ->and($second->wasRecentlyCreated)->toBeFalse()
        ->and($third->id)->not->toBe($first->id)
        ->and(Order::count())->toBe(2);
});

it('scopes an idempotency key to its customer', function () {
    $vehicle = Vehicle::factory()->create();
    $items = [['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-14 09:00', 'end_at' => '2026-10-16 09:00']];
    $firstCustomer = User::factory()->create();

    app(CreateOrder::class)->handle($firstCustomer, $items, 'sama');

    expect(fn () => app(CreateOrder::class)->handle(User::factory()->create(), $items, 'sama'))->toThrow(ValidationException::class)
        ->and(Order::count())->toBe(1);
});
