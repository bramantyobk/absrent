<?php

use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function unconfirmedOrder(string $expiresAt, array $transaction = []): Order
{
    $order = orderWithTransaction(['expires_at' => $expiresAt], $transaction + ['status' => TransactionStatus::Pending, 'bank_account_id' => null, 'confirmed_at' => null]);
    $order->items()->update(['start_at' => '2026-10-20 08:00:00', 'end_at' => '2026-10-22 08:00:00']);

    return $order;
}

it('rejects guests and customers', function () {
    $order = unconfirmedOrder('2026-10-12 09:00:00');

    $this->post(route('dashboard.orders.cancel', $order))->assertRedirect(route('login'));
    $this->postJson(route('api.v1.dashboard.orders.cancel', $order))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->post(route('dashboard.orders.cancel', $order))->assertForbidden();
});

it('lets staff cancel an order past its confirmation deadline, recording who and why, and releasing the vehicle', function (string $role) {
    $staff = User::factory()->{$role}()->create(['name' => 'Siti Operator']);
    $order = unconfirmedOrder('2026-10-12 09:59:00');
    $item = $order->items->first();

    $this->actingAs($staff)->post(route('dashboard.orders.cancel', $order))
        ->assertRedirect(route('dashboard.orders.show', $order))
        ->assertSessionHasNoErrors();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->status_note)->toBe('Dibatalkan oleh Siti Operator: melewati batas konfirmasi')
        ->and($order->transaction->status)->toBe(TransactionStatus::Cancelled)
        ->and($order->transaction->note)->toBe('Dibatalkan oleh Siti Operator: melewati batas konfirmasi')
        ->and(Vehicle::query()->availableBetween($item->start_at, $item->end_at)->whereKey($item->vehicle_id)->exists())->toBeTrue();
})->with(['operator', 'admin']);

it('rejects cancelling before the deadline', function () {
    $order = unconfirmedOrder('2026-10-12 10:00:01');

    $this->actingAs(User::factory()->operator()->create())->post(route('dashboard.orders.cancel', $order))->assertSessionHasErrors('order');

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('rejects cancelling orders the customer already confirmed or that are in another state', function (OrderStatus $status, TransactionStatus $transactionStatus) {
    $order = orderWithTransaction(['status' => $status, 'expires_at' => '2026-10-12 09:00:00'], ['status' => $transactionStatus]);

    $this->actingAs(User::factory()->operator()->create())->post(route('dashboard.orders.cancel', $order))->assertSessionHasErrors('order');

    expect($order->refresh()->status)->toBe($status)->and($order->transaction->status)->toBe($transactionStatus);
})->with([
    'confirmed, awaiting verification' => [OrderStatus::Pending, TransactionStatus::Verifying],
    'paid booking' => [OrderStatus::Booking, TransactionStatus::Paid],
    'already cancelled' => [OrderStatus::Cancelled, TransactionStatus::Cancelled],
]);

it('shows the cancel action only past the deadline for unconfirmed orders', function () {
    $this->actingAs(User::factory()->operator()->create());

    $this->get(route('dashboard.orders.show', unconfirmedOrder('2026-10-12 09:00:00')))->assertSee('Melewati batas konfirmasi')->assertSee('cancelExpiredModal');
    $this->get(route('dashboard.orders.show', unconfirmedOrder('2026-10-13 09:00:00')))->assertDontSee('cancelExpiredModal');
    $this->get(route('dashboard.orders.show', orderWithTransaction(['expires_at' => '2026-10-12 09:00:00'])))->assertDontSee('cancelExpiredModal');
});

it('points from the transaction page of an unconfirmed order to the order page', function () {
    $order = unconfirmedOrder('2026-10-12 09:00:00');

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.transactions.show', $order->transaction))
        ->assertSee('Pelanggan belum mengonfirmasi pembayaran')
        ->assertSee(route('dashboard.orders.show', $order), false);
});

it('cancels through the api and rejects invalid cases with 422', function () {
    $overdue = unconfirmedOrder('2026-10-12 09:00:00');
    $early = unconfirmedOrder('2026-10-13 09:00:00');
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->postJson(route('api.v1.dashboard.orders.cancel', $overdue))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.status_note', fn (string $note) => str_contains($note, 'melewati batas konfirmasi'))
        ->assertJsonPath('data.transaction.status', 'cancelled');

    $this->postJson(route('api.v1.dashboard.orders.cancel', $early))->assertUnprocessable()->assertJsonValidationErrors('order');
});
