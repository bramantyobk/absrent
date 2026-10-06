<?php

use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use App\UserRole;

beforeEach(function () {
    $this->seed();
});

it('seeds staff accounts, company data, and vehicles', function () {
    expect(User::where('role', UserRole::Admin)->count())->toBe(1)
        ->and(User::where('role', UserRole::Operator)->where('is_on_duty', true)->count())->toBe(2)
        ->and(CompanyProfile::count())->toBe(1)
        ->and(BankAccount::where('is_active', true)->count())->toBe(3)
        ->and(Vehicle::count())->toBe(12);
});

it('keeps order totals consistent with their items and transaction amount', function () {
    Order::with(['items', 'transaction'])->each(function (Order $order) {
        expect($order->total_price)->toBe((int) $order->items->sum('subtotal'))
            ->and($order->transaction->amount)->toBe($order->total_price);
    });
});

it('creates a transaction state that matches each order status', function () {
    Order::with('transaction')->where('status', OrderStatus::Pending)->each(
        fn (Order $order) => expect($order->transaction->status)
            ->toBeIn([TransactionStatus::Pending, TransactionStatus::Verifying])
    );

    Order::with('transaction')->whereIn('status', [OrderStatus::Booking, OrderStatus::Rented, OrderStatus::Completed])->each(
        fn (Order $order) => expect($order->transaction->status)->toBe(TransactionStatus::Paid)
    );

    Order::with('transaction')->where('status', OrderStatus::Failed)->each(
        fn (Order $order) => expect($order->transaction->status)->toBe(TransactionStatus::Failed)
    );
});

it('does not double-book a vehicle across active orders', function () {
    $windows = Order::with('items')
        ->whereIn('status', [OrderStatus::Pending, OrderStatus::Booking, OrderStatus::Rented, OrderStatus::Completed])
        ->get()
        ->flatMap->items
        ->groupBy('vehicle_id');

    $windows->each(function ($items) {
        $sorted = $items->sortBy('start_at')->values();

        foreach ($sorted as $index => $item) {
            if ($index > 0) {
                expect($item->start_at->gte($sorted[$index - 1]->end_at))->toBeTrue();
            }
        }
    });
});
