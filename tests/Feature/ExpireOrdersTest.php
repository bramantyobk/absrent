<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function expiringOrder(OrderStatus $status, TransactionStatus $transactionStatus, string $expiresAt): Order
{
    $order = Order::factory()->create(['status' => $status, 'expires_at' => $expiresAt]);
    OrderItem::factory()->for($order, 'order')->for(Vehicle::factory()->create(), 'vehicle')->create();
    Transaction::factory()->for($order)->create(['status' => $transactionStatus]);

    return $order;
}

it('cancels unconfirmed pending orders past their deadline and releases the vehicle', function () {
    $order = expiringOrder(OrderStatus::Pending, TransactionStatus::Pending, '2026-10-12 09:59:00');
    $item = $order->items()->first();

    $this->artisan('orders:expire')->expectsOutputToContain('1 pesanan dibatalkan, 0 pesanan digagalkan')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->transaction->status)->toBe(TransactionStatus::Cancelled)
        ->and(Vehicle::query()->availableBetween($item->start_at, $item->end_at)->whereKey($item->vehicle_id)->exists())->toBeTrue();
});

it('fails transactions awaiting verification past the verification deadline, with a note', function () {
    $order = expiringOrder(OrderStatus::Pending, TransactionStatus::Verifying, '2026-10-12 10:00:00');

    $this->artisan('orders:expire')->expectsOutputToContain('0 pesanan dibatalkan, 1 pesanan digagalkan')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Failed)
        ->and($order->transaction->status)->toBe(TransactionStatus::Failed)
        ->and($order->transaction->note)->toBe('Verifikasi tidak dilakukan dalam batas waktu');
});

it('leaves orders before their deadline and orders already past payment untouched', function (OrderStatus $status, TransactionStatus $transactionStatus, string $expiresAt) {
    $order = expiringOrder($status, $transactionStatus, $expiresAt);

    $this->artisan('orders:expire')->assertSuccessful();

    expect($order->refresh()->status)->toBe($status)
        ->and($order->transaction->status)->toBe($transactionStatus);
})->with([
    'pending before deadline' => [OrderStatus::Pending, TransactionStatus::Pending, '2026-10-12 10:00:01'],
    'verifying before deadline' => [OrderStatus::Pending, TransactionStatus::Verifying, '2026-10-12 10:00:01'],
    'paid booking' => [OrderStatus::Booking, TransactionStatus::Paid, '2026-10-01 00:00:00'],
    'rented' => [OrderStatus::Rented, TransactionStatus::Paid, '2026-10-01 00:00:00'],
]);

it('runs the expiry command every minute through the scheduler', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'orders:expire'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *');
});
