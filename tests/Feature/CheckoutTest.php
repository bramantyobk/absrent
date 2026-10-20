<?php

use App\Actions\Orders\CreateOrder;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function rentalPeriod(array $overrides = []): array
{
    return $overrides + ['start_at' => '2026-10-14T09:00', 'days' => 2];
}

function orderItems(Vehicle ...$vehicles): array
{
    return array_map(fn (Vehicle $vehicle): int => $vehicle->id, $vehicles);
}

it('requires a customer account for checkout and orders', function (string $method, string $route) {
    $this->{$method}(route($route, [Vehicle::factory()->create()]))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route, [Vehicle::factory()->create()]))->assertForbidden();
})->with([
    ['get', 'checkout.show'],
    ['post', 'checkout.store'],
    ['post', 'checkout.items.store'],
    ['post', 'checkout.reschedule'],
    ['get', 'orders.index'],
]);

it('adds a vehicle with its period to the checkout and shows the schedule, days, and subtotal', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio', 'price_per_day' => 75000]);

    $this->actingAs(User::factory()->create())
        ->post(route('checkout.items.store', $vehicle), rentalPeriod())
        ->assertRedirect(route('checkout.show'))
        ->assertSessionHasNoErrors();

    $this->get(route('checkout.show'))
        ->assertOk()
        ->assertSee('Honda Genio')
        ->assertSee('14/10/2026 09:00 WIB')
        ->assertSee('16/10/2026 09:00 WIB')
        ->assertSee('2 hari')
        ->assertSee('Rp150.000')
        ->assertSee('Tambah kendaraan')
        ->assertSee('Ubah jadwal');
});

it('rejects periods that break the rental rules', function (array $period, string $field) {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('checkout.items.store', $vehicle), $period)
        ->assertSessionHasErrors($field);

    expect(session('checkout.vehicles', []))->toBeEmpty();
})->with([
    'start in the past' => [['start_at' => '2026-10-11T09:00', 'days' => 2], 'start_at'],
    'zero days' => [['start_at' => '2026-10-14T09:00', 'days' => 0], 'days'],
    'more days than the vehicle allows' => [['start_at' => '2026-10-14T09:00', 'days' => 8], 'days'],
    'missing days' => [['start_at' => '2026-10-14T09:00'], 'days'],
    'missing start' => [['days' => 2], 'start_at'],
]);

it('accepts exactly 1 day and exactly 7 days', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('checkout.items.store', Vehicle::factory()->create()), ['start_at' => '2026-10-14T09:00', 'days' => 1])->assertSessionHasNoErrors();
    $this->post(route('checkout.reschedule'));
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), ['start_at' => '2026-10-14T09:00', 'days' => 7])->assertSessionHasNoErrors();
});

it('explains why a period is rejected', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $this->actingAs(User::factory()->create());

    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-11T09:00', 'days' => 2])
        ->assertSessionHasErrors(['start_at' => 'Waktu mulai tidak boleh di masa lalu.']);
    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-14T09:00', 'days' => 8])
        ->assertSessionHasErrors(['days' => 'Durasi sewa maksimal 7 hari untuk Honda Genio.']);

    config(['rental.min_rental_hours' => 48]);

    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-14T09:00', 'days' => 1])
        ->assertSessionHasErrors(['days' => 'Durasi sewa minimal 2 hari.']);
});

it('applies the per-vehicle rental limit', function () {
    $vehicle = Vehicle::factory()->create(['max_rental_days' => 3]);
    $this->actingAs(User::factory()->create());

    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-14T09:00', 'days' => 4])->assertSessionHasErrors('days');
    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-14T09:00', 'days' => 3])->assertSessionHasNoErrors();
});

it('keeps the return buffer after an order and before the next one', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Pending, '2026-10-14 08:00', '2026-10-15 08:00');
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-21 08:00');
    $customer = User::factory()->create();

    $accepts = function (string $start, int $days) use ($vehicle, $customer): bool {
        $this->actingAs($customer)->post(route('checkout.reschedule'));
        $this->post(route('checkout.items.store', $vehicle), ['start_at' => $start, 'days' => $days]);

        return ! session()->has('errors') && session('checkout.vehicles') === [$vehicle->id];
    };

    expect($accepts('2026-10-15T10:59', 1))->toBeFalse()
        ->and($accepts('2026-10-15T11:00', 1))->toBeTrue()
        ->and($accepts('2026-10-17T04:00', 3))->toBeTrue()
        ->and($accepts('2026-10-17T05:01', 3))->toBeFalse();
});

it('rejects a period held by maintenance', function () {
    $vehicle = Vehicle::factory()->create();
    VehicleSchedule::factory()->for($vehicle)->between('2026-10-14 08:00', '2026-10-15 08:00')->create();
    $other = Vehicle::factory()->create();
    VehicleSchedule::factory()->for($other)->between('2026-10-20 12:00', '2026-10-20 13:00')->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('checkout.items.store', $vehicle), rentalPeriod())->assertSessionHasErrors('start_at');
    $this->post(route('checkout.items.store', $other), ['start_at' => '2026-10-18T09:00', 'days' => 2])->assertSessionHasNoErrors();
});

it('does not let a vehicle hidden from the catalog be added', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('checkout.items.store', Vehicle::factory()->unavailable()->create()), rentalPeriod())
        ->assertNotFound();
});

it('lets one more vehicle be added only with the same period of the first', function () {
    $genio = Vehicle::factory()->create();
    $beat = Vehicle::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', $genio), rentalPeriod());

    $this->post(route('checkout.items.store', $beat), rentalPeriod(['start_at' => '2026-10-14T10:00']))->assertSessionHasErrors(['start_at' => 'Kendaraan tambahan harus memakai jadwal yang sama dengan pesanan ini.']);
    $this->post(route('checkout.items.store', $beat), rentalPeriod(['days' => 3]))->assertSessionHasErrors('start_at');
    expect(session('checkout.vehicles'))->toBe([$genio->id]);

    $this->post(route('checkout.items.store', $beat), rentalPeriod())->assertSessionHasNoErrors();
    expect(session('checkout.vehicles'))->toBe([$genio->id, $beat->id]);
});

it('limits an order to two vehicles and rejects the same vehicle twice', function () {
    $first = Vehicle::factory()->create();
    $second = Vehicle::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', $first), rentalPeriod());

    $this->post(route('checkout.items.store', $first), rentalPeriod())->assertSessionHasErrors(['start_at' => 'Kendaraan ini sudah ada di pesanan Anda.']);
    $this->post(route('checkout.items.store', $second), rentalPeriod())->assertSessionHasNoErrors();
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod())->assertSessionHasErrors(['start_at' => 'Satu pesanan maksimal 2 kendaraan.']);

    $this->get(route('checkout.show'))
        ->assertDontSee('Tambah kendaraan')
        ->assertDontSee('Ubah jadwal')
        ->assertSee('hapus semua kendaraan');
});

it('offers the vehicle list for the same period when adding another vehicle', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod());

    $this->get(route('checkout.show'))->assertSee(e(route('vehicles.index', ['start_at' => '2026-10-14T09:00', 'days' => 2])), false);
});

it('locks the period on the detail page of another vehicle', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod());

    $this->get(route('vehicles.show', Vehicle::factory()->create()))
        ->assertSee('Jadwal mengikuti pesanan Anda')
        ->assertSee('14/10/2026 09:00 WIB, 2 hari')
        ->assertSee('<input type="hidden" name="start_at" value="2026-10-14T09:00">', false)
        ->assertDontSee('data-quote-start', false);
});

it('removes an item and forgets the period when the order becomes empty', function () {
    $genio = Vehicle::factory()->create(['model' => 'Satu']);
    $beat = Vehicle::factory()->create(['model' => 'Dua']);
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', $genio), rentalPeriod());
    $this->post(route('checkout.items.store', $beat), rentalPeriod());

    $this->delete(route('checkout.items.destroy', 0))->assertRedirect(route('checkout.show'));
    $this->get(route('checkout.show'))->assertDontSee('Satu')->assertSee('Dua');

    $this->delete(route('checkout.items.destroy', 0));
    expect(session('checkout.period'))->toBeNull();

    $this->post(route('checkout.items.store', $genio), ['start_at' => '2026-10-18T09:00', 'days' => 1])->assertSessionHasNoErrors();
});

it('reschedules an order with one vehicle by returning to its detail page with the period filled in', function () {
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', $vehicle), rentalPeriod());

    $this->post(route('checkout.reschedule'))
        ->assertRedirect(route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => '2026-10-14T09:00', 'days' => 2]));

    expect(session('checkout.vehicles', []))->toBeEmpty()->and(session('checkout.period'))->toBeNull();
});

it('does not reschedule an order with two vehicles', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod());
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod());

    $this->post(route('checkout.reschedule'))
        ->assertRedirect(route('checkout.show'))
        ->assertSessionHas('error');

    expect(session('checkout.vehicles'))->toHaveCount(2);
});

it('shows how many vehicles are in the order in the navbar', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod());
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod());

    $this->get(route('home'))->assertSee('<span class="badge text-bg-primary ms-1">2</span>', false);
});

it('creates a pending order with items on one period and a pending transaction, then clears the checkout', function () {
    $customer = User::factory()->create();
    $genio = Vehicle::factory()->create(['price_per_day' => 75000]);
    $avanza = Vehicle::factory()->car()->create(['price_per_day' => 350000]);
    $this->actingAs($customer);
    $this->post(route('checkout.items.store', $genio), rentalPeriod(['days' => 2]));
    $this->post(route('checkout.items.store', $avanza), rentalPeriod(['days' => 2]));

    $this->post(route('checkout.store'), ['terms_accepted' => 1])->assertRedirect()->assertSessionHasNoErrors();

    $order = Order::query()->with(['items', 'transaction'])->sole();

    expect($order->user_id)->toBe($customer->id)
        ->and($order->code)->toMatch('/^ABS-[A-Z0-9]{8}$/')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_price)->toBe(850000)
        ->and($order->expires_at->toDateTimeString())->toBe('2026-10-13 10:00:00')
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->pluck('start_at')->map->toDateTimeString()->unique()->all())->toBe(['2026-10-14 09:00:00'])
        ->and($order->items->pluck('end_at')->map->toDateTimeString()->unique()->all())->toBe(['2026-10-16 09:00:00'])
        ->and($order->items->firstWhere('vehicle_id', $genio->id))->toMatchArray(['days' => 2, 'unit_price' => 75000, 'subtotal' => 150000])
        ->and($order->transaction->status)->toBe(TransactionStatus::Pending)
        ->and($order->transaction->amount)->toBe($order->total_price)
        ->and(session('checkout.vehicles', []))->toBeEmpty()
        ->and(session('checkout.period'))->toBeNull();
});

it('rejects an empty checkout', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('checkout.store'), ['terms_accepted' => 1])
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

    $this->post(route('checkout.store'), ['terms_accepted' => 1])->assertSessionHasErrors('items.0.vehicle_id');

    expect(Order::count())->toBe($before)->and(session('checkout.vehicles'))->toHaveCount(1);
    $this->get(route('checkout.show'))->assertSee('Kendaraan tidak tersedia pada rentang waktu ini.');
});

it('rejects an empty list, the same vehicle twice, and more vehicles than allowed when creating an order directly', function () {
    $customer = User::factory()->create();
    $same = Vehicle::factory()->create()->id;
    $three = Vehicle::factory()->count(3)->create()->pluck('id')->all();

    foreach ([[], [$same, $same], $three] as $vehicleIds) {
        expect(fn () => app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, $vehicleIds))->toThrow(ValidationException::class);
    }

    expect(Order::count())->toBe(0);
});

it('does not create a second order for a repeated idempotency key', function () {
    $vehicle = Vehicle::factory()->create();
    $customer = User::factory()->create();

    $first = app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, orderItems($vehicle), 'kunci-1');
    $second = app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, orderItems($vehicle), 'kunci-1');
    $third = app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, orderItems(Vehicle::factory()->create()), 'kunci-2');

    expect($second->id)->toBe($first->id)
        ->and($second->wasRecentlyCreated)->toBeFalse()
        ->and($third->id)->not->toBe($first->id)
        ->and(Order::count())->toBe(2);
});

it('scopes an idempotency key to its customer', function () {
    $vehicle = Vehicle::factory()->create();

    app(CreateOrder::class)->handle(User::factory()->create(), '2026-10-14 09:00', 2, orderItems($vehicle), 'sama');

    expect(fn () => app(CreateOrder::class)->handle(User::factory()->create(), '2026-10-14 09:00', 2, orderItems($vehicle), 'sama'))->toThrow(ValidationException::class)
        ->and(Order::count())->toBe(1);
});

it('blocks a customer who already has the maximum number of pending orders', function () {
    $customer = User::factory()->create();
    Order::factory()->count(3)->for($customer)->create();

    $this->actingAs($customer);
    $this->post(route('checkout.items.store', Vehicle::factory()->create()), rentalPeriod());
    $this->post(route('checkout.store'), ['terms_accepted' => 1])->assertSessionHasErrors('items');

    expect(Order::count())->toBe(3)->and(session('checkout.vehicles'))->toHaveCount(1);
});

it('counts only pending orders toward the limit and honours the configured limit', function () {
    $customer = User::factory()->create();
    Order::factory()->count(2)->for($customer)->booking()->create();
    Order::factory()->count(2)->for($customer)->cancelled()->create();
    Order::factory()->for(User::factory()->create())->create();

    expect(app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, orderItems(Vehicle::factory()->create())))->toBeInstanceOf(Order::class);

    config(['rental.max_pending_orders' => 1]);

    expect(fn () => app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, orderItems(Vehicle::factory()->create())))->toThrow(ValidationException::class);
});

it('asks the client to retry when the idempotency key is still being processed', function () {
    config(['rental.checkout_lock_wait_seconds' => 0]);
    $customer = User::factory()->create();
    $vehicle = Vehicle::factory()->create();
    Cache::lock("checkout:{$customer->id}:sibuk:lock", 10)->get();

    expect(fn () => app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, orderItems($vehicle), 'sibuk'))
        ->toThrow(ValidationException::class, 'sedang diproses');

    expect(Order::count())->toBe(0);
});

it('retries with a new code when the generated order code already exists', function () {
    Str::createRandomStringsUsingSequence(['AAAAAAAA', 'AAAAAAAA', 'BBBBBBBB']);
    $customer = User::factory()->create();

    $first = app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, orderItems(Vehicle::factory()->create()));
    $second = app(CreateOrder::class)->handle($customer, '2026-10-14 09:00', 2, orderItems(Vehicle::factory()->create()));

    Str::createRandomStringsNormally();

    expect($first->code)->toBe('ABS-AAAAAAAA')->and($second->code)->toBe('ABS-BBBBBBBB');
});
