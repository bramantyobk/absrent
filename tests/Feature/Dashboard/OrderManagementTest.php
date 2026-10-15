<?php

use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

it('rejects guests and customers on the order management routes', function (string $method, string $route) {
    $order = orderWithTransaction();

    $this->{$method}(route($route, $order))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->{$method}(route($route, $order))->assertForbidden();
})->with([
    ['get', 'dashboard.orders.index'],
    ['get', 'dashboard.orders.show'],
    ['patch', 'dashboard.orders.status.update'],
]);

it('lets operators and admins list orders, newest first, with the pending cancellation marker', function (string $role) {
    $older = orderWithTransaction(['code' => 'ABS-OLDER001']);
    $newer = orderWithTransaction(['code' => 'ABS-NEWER002', 'status' => OrderStatus::Booking]);
    CancellationRequest::factory()->for($newer)->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('dashboard.orders.index'))
        ->assertOk()
        ->assertSeeInOrder(['ABS-NEWER002', 'ABS-OLDER001'])
        ->assertSee('Ajuan batal');
})->with(['operator', 'admin']);

it('filters orders by search, status, category, and start date range', function () {
    $genio = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio', 'plate_number' => 'B 1111 AAA', 'type' => 'motorcycle']);
    $avanza = Vehicle::factory()->car()->create(['brand' => 'Toyota', 'model' => 'Avanza', 'plate_number' => 'D 2222 BBB']);
    $budi = orderWithTransaction(['code' => 'ABS-FILTER01', 'user_id' => User::factory()->create(['name' => 'Budi Santoso'])], vehicle: $genio);
    $budi->items()->update(['start_at' => '2026-10-20 08:00:00', 'end_at' => '2026-10-21 08:00:00']);
    $siti = orderWithTransaction(['code' => 'ABS-FILTER02', 'status' => OrderStatus::Booking, 'user_id' => User::factory()->create(['name' => 'Siti Aminah'])], vehicle: $avanza);
    $siti->items()->update(['start_at' => '2026-11-05 08:00:00', 'end_at' => '2026-11-06 08:00:00']);
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('dashboard.orders.index', ['search' => 'Budi']))->assertSee('ABS-FILTER01')->assertDontSee('ABS-FILTER02');
    $this->get(route('dashboard.orders.index', ['search' => 'D 2222']))->assertSee('ABS-FILTER02')->assertDontSee('ABS-FILTER01');
    $this->get(route('dashboard.orders.index', ['search' => 'FILTER02']))->assertSee('ABS-FILTER02')->assertDontSee('ABS-FILTER01');
    $this->get(route('dashboard.orders.index', ['status' => 'booking']))->assertSee('ABS-FILTER02')->assertDontSee('ABS-FILTER01');
    $this->get(route('dashboard.orders.index', ['type' => 'motorcycle']))->assertSee('ABS-FILTER01')->assertDontSee('ABS-FILTER02');
    $this->get(route('dashboard.orders.index', ['from' => '2026-11-01', 'to' => '2026-11-30']))->assertSee('ABS-FILTER02')->assertDontSee('ABS-FILTER01');
    $this->get(route('dashboard.orders.index', ['from' => '2026-11-30', 'to' => '2026-11-01']))->assertSessionHasErrors('to');
});

it('shows the order detail with customer, items, transaction, and cancellation history', function () {
    $order = orderWithTransaction(['code' => 'ABS-DETAIL01', 'status' => OrderStatus::Booking, 'user_id' => User::factory()->create(['name' => 'Budi Santoso'])]);
    CancellationRequest::factory()->for($order)->create(['reason' => 'Jadwal perjalanan berubah total.']);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.orders.show', $order))
        ->assertOk()
        ->assertSee('ABS-DETAIL01')
        ->assertSee('Budi Santoso')
        ->assertSee('Rp300.000')
        ->assertSee('Jadwal perjalanan berubah total.')
        ->assertSee('Ubah menjadi Disewa');
});

it('moves an order from booking to rented to completed', function () {
    $order = orderWithTransaction(['status' => OrderStatus::Booking], ['status' => TransactionStatus::Paid]);
    $this->actingAs(User::factory()->operator()->create());

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'rented'])->assertRedirect(route('dashboard.orders.show', $order));
    expect($order->refresh()->status)->toBe(OrderStatus::Rented);

    $this->patch(route('dashboard.orders.status.update', $order), ['status' => 'completed'])->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe(OrderStatus::Completed);
});

it('rejects skipped, backward, unsupported, and repeated status changes', function (OrderStatus $from, string $to) {
    $order = orderWithTransaction(['status' => $from], ['status' => TransactionStatus::Paid]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('dashboard.orders.status.update', $order), ['status' => $to])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe($from);
})->with([
    'pending to rented' => [OrderStatus::Pending, 'rented'],
    'booking to completed' => [OrderStatus::Booking, 'completed'],
    'rented to rented' => [OrderStatus::Rented, 'rented'],
    'completed to rented' => [OrderStatus::Completed, 'rented'],
    'booking to stolen' => [OrderStatus::Booking, 'stolen'],
    'booking to refunded' => [OrderStatus::Booking, 'refunded'],
]);

it('keeps the vehicle held while rented and releases it once completed', function () {
    $order = orderWithTransaction(['status' => OrderStatus::Rented], ['status' => TransactionStatus::Paid]);
    $item = $order->items->first();
    $item->update(['start_at' => '2026-10-10 08:00:00', 'end_at' => '2026-10-11 08:00:00']);
    $available = fn (): bool => Vehicle::query()->availableAt(now()->addDay())->whereKey($item->vehicle_id)->exists();

    expect($available())->toBeFalse();

    $this->actingAs(User::factory()->operator()->create())->patch(route('dashboard.orders.status.update', $order), ['status' => 'completed']);

    expect($available())->toBeTrue();
});

it('shows the sidebar menus for transactions and cancellations', function () {
    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard'))
        ->assertSee(route('dashboard.transactions.index'), false)
        ->assertSee(route('dashboard.cancellation-requests.index'), false)
        ->assertSee(route('dashboard.orders.index'), false);
});

it('flags transactions waiting for verification and pending cancellations on the dashboard', function () {
    orderWithTransaction();
    orderWithTransaction(['expires_at' => now()->addHour()]);
    CancellationRequest::factory()->create();

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard'))
        ->assertSee('2 transaksi menunggu verifikasi')
        ->assertSee('1 hampir habis waktunya')
        ->assertSee('1 pengajuan pembatalan menunggu keputusan');
});

it('hides the dashboard attention banner when nothing waits', function () {
    $this->actingAs(User::factory()->operator()->create())->get(route('dashboard'))->assertDontSee('menunggu verifikasi');
});

it('does not touch other orders when changing one status', function () {
    $first = orderWithTransaction(['status' => OrderStatus::Booking], ['status' => TransactionStatus::Paid]);
    $second = orderWithTransaction(['status' => OrderStatus::Booking], ['status' => TransactionStatus::Paid]);

    $this->actingAs(User::factory()->operator()->create())->patch(route('dashboard.orders.status.update', $first), ['status' => 'rented']);

    expect($second->refresh()->status)->toBe(OrderStatus::Booking)->and(Order::count())->toBe(2);
});
