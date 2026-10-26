<?php

use App\CancellationStatus;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function paidBooking(User $customer, array $orderAttributes = []): Order
{
    return orderWithTransaction($orderAttributes + ['user_id' => $customer->id, 'status' => OrderStatus::Booking, 'expires_at' => null], ['status' => TransactionStatus::Paid]);
}

it('requires a customer account to request cancellation', function () {
    $order = paidBooking(User::factory()->create());

    $this->post(route('orders.cancellation-request', $order))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->operator()->create())->post(route('orders.cancellation-request', $order))->assertForbidden();
});

it('lets a customer request cancellation of a paid booking with a reason, leaving the order unchanged', function () {
    $customer = User::factory()->create();
    $order = paidBooking($customer);

    $this->actingAs($customer)
        ->post(route('orders.cancellation-request', $order), ['reason' => 'Jadwal perjalanan berubah.'])
        ->assertRedirectContains('https://wa.me/');

    $request = CancellationRequest::sole();

    expect($request->order_id)->toBe($order->id)
        ->and($request->user_id)->toBe($customer->id)
        ->and($request->reason)->toBe('Jadwal perjalanan berubah.')
        ->and($request->status)->toBe(CancellationStatus::Pending)
        ->and($order->refresh()->status)->toBe(OrderStatus::Booking);
});

it('shows the request form on a paid booking and the request status afterwards', function () {
    $customer = User::factory()->create();
    $order = paidBooking($customer);

    $this->actingAs($customer)->get(route('orders.show', $order))->assertSee('Ajukan Pembatalan')
        ->assertSee('target="_blank" data-payment-confirm', false)
        ->assertSee('data-after-url="'.route('orders.show', $order).'"', false);

    $this->post(route('orders.cancellation-request', $order), ['reason' => 'Jadwal perjalanan berubah.']);

    $this->get(route('orders.show', $order))->assertDontSee('Ajukan Pembatalan')->assertSee('Pengajuan pembatalan: Menunggu')->assertSee(route('orders.cancellation-whatsapp', $order));
});

it('rejects a cancellation request before payment, once rented, with a pending request, or for another customer', function () {
    $customer = User::factory()->create();
    $reason = ['reason' => 'Jadwal perjalanan berubah.'];
    $this->actingAs($customer);

    foreach ([OrderStatus::Pending, OrderStatus::Rented, OrderStatus::Completed, OrderStatus::Cancelled] as $status) {
        $order = orderWithTransaction(['user_id' => $customer->id, 'status' => $status], ['status' => TransactionStatus::Paid]);
        $this->post(route('orders.cancellation-request', $order), $reason)->assertForbidden();
    }

    $unpaid = orderWithTransaction(['user_id' => $customer->id]);
    $this->post(route('orders.cancellation-request', $unpaid), $reason)->assertForbidden();

    $booking = paidBooking($customer);
    $this->post(route('orders.cancellation-request', $booking), $reason)->assertSessionHasNoErrors();
    $this->post(route('orders.cancellation-request', $booking), $reason)->assertForbidden();

    $this->actingAs(User::factory()->create())->post(route('orders.cancellation-request', paidBooking($customer)), $reason)->assertNotFound();

    expect(CancellationRequest::count())->toBe(1);
});

it('requires a reason of reasonable length', function (?string $reason) {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->post(route('orders.cancellation-request', paidBooking($customer)), ['reason' => $reason])
        ->assertSessionHasErrors('reason');
})->with([null, 'ya', 'a very long reason' => [str_repeat('a', 1001)]]);

it('lists pending requests first for staff, and denies customers', function () {
    CancellationRequest::factory()->approved()->create(['reason' => 'Sudah selesai diproses.']);
    CancellationRequest::factory()->create(['reason' => 'Menunggu keputusan staf.']);

    $this->actingAs(User::factory()->create())->get(route('dashboard.cancellation-requests.index'))->assertForbidden();

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.cancellation-requests.index'))
        ->assertOk()
        ->assertSeeInOrder(['Menunggu keputusan staf.', 'Sudah selesai diproses.']);

    $this->get(route('dashboard.cancellation-requests.index', ['status' => 'approved']))->assertSee('Sudah selesai diproses.')->assertDontSee('Menunggu keputusan staf.');
});

it('approves a request: the order becomes refunded, the transaction cancelled, and the vehicle released', function () {
    $operator = User::factory()->operator()->create();
    $vehicle = Vehicle::factory()->create();
    $order = paidBooking(User::factory()->create(), []);
    $order->items()->update(['vehicle_id' => $vehicle->id, 'start_at' => '2026-10-20 08:00:00', 'end_at' => '2026-10-21 08:00:00']);
    $request = CancellationRequest::factory()->for($order)->create();
    $isFree = fn (): bool => Vehicle::query()->availableBetween(now()->setDate(2026, 10, 20)->setTime(8, 0), now()->setDate(2026, 10, 21)->setTime(8, 0))->whereKey($vehicle->id)->exists();

    expect($isFree())->toBeFalse();

    $this->actingAs($operator)
        ->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'approved', 'note' => 'Dana dikembalikan lewat transfer BCA.'])
        ->assertRedirect(route('dashboard.cancellation-requests.index'));

    $request->refresh();

    expect($request->status)->toBe(CancellationStatus::Approved)
        ->and($request->handled_by)->toBe($operator->id)
        ->and($request->handled_note)->toBe('Dana dikembalikan lewat transfer BCA.')
        ->and($request->handled_at->toDateTimeString())->toBe('2026-10-12 10:00:00')
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->transaction->status)->toBe(TransactionStatus::Cancelled)
        ->and($isFree())->toBeTrue();
});

it('rejects a request without changing the order', function () {
    $order = paidBooking(User::factory()->create());
    $request = CancellationRequest::factory()->for($order)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'rejected', 'note' => 'Terlalu dekat dengan jadwal sewa.']);

    expect($request->refresh()->status)->toBe(CancellationStatus::Rejected)
        ->and($order->refresh()->status)->toBe(OrderStatus::Booking)
        ->and($order->transaction->status)->toBe(TransactionStatus::Paid);
});

it('allows a new request after the previous one was rejected', function () {
    $customer = User::factory()->create();
    $order = paidBooking($customer);
    CancellationRequest::factory()->for($order)->rejected()->create();

    $this->actingAs($customer)->post(route('orders.cancellation-request', $order), ['reason' => 'Mohon ditinjau kembali.'])->assertSessionHasNoErrors();

    expect(CancellationRequest::count())->toBe(2);
});

it('cannot approve once the order is rented, only reject', function () {
    $order = paidBooking(User::factory()->create(), ['status' => OrderStatus::Rented]);
    $request = CancellationRequest::factory()->for($order)->create();
    $this->actingAs(User::factory()->operator()->create());

    $this->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'approved', 'note' => 'Refund.'])->assertSessionHasErrors('decision');

    expect($request->refresh()->status)->toBe(CancellationStatus::Pending)->and($order->refresh()->status)->toBe(OrderStatus::Rented);

    $this->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'rejected', 'note' => 'Unit sudah berjalan.'])->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(CancellationStatus::Rejected);
});

it('requires a decision and a note, and decides each request only once', function () {
    $request = CancellationRequest::factory()->for(paidBooking(User::factory()->create()))->create();
    $this->actingAs(User::factory()->operator()->create());

    $this->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'approved'])->assertSessionHasErrors('note');
    $this->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'pending', 'note' => 'x'])->assertSessionHasErrors('decision');

    $this->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'rejected', 'note' => 'Ditolak.']);
    $this->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'approved', 'note' => 'Refund.'])->assertSessionHasErrors('decision');

    expect($request->refresh()->status)->toBe(CancellationStatus::Rejected);
});

it('forbids customers from deciding requests', function () {
    $customer = User::factory()->create();
    $request = CancellationRequest::factory()->for(paidBooking($customer))->create();

    $this->actingAs($customer)->patch(route('dashboard.cancellation-requests.update', $request), ['decision' => 'approved', 'note' => 'Refund.'])->assertForbidden();

    expect($request->refresh()->status)->toBe(CancellationStatus::Pending);
});

it('redirects the cancellation request to the assigned operator WhatsApp with the order code and reason', function () {
    $customer = User::factory()->create();
    $operator = User::factory()->operator()->create(['phone' => '6281234567890']);
    $order = paidBooking($customer, ['assigned_operator_id' => $operator->id]);

    $response = $this->actingAs($customer)->post(route('orders.cancellation-request', $order), ['reason' => 'Jadwal perjalanan berubah.']);

    $response->assertRedirectContains('https://wa.me/6281234567890?text=');

    $text = urldecode(explode('?text=', $response->headers->get('Location'))[1]);

    expect($text)->toContain($order->code)->toContain('Alasan: Jadwal perjalanan berubah.');
});

it('reopens WhatsApp only for a pending cancellation request of the own order', function () {
    $customer = User::factory()->create();
    $operator = User::factory()->operator()->create(['phone' => '6281234567890']);
    $order = paidBooking($customer, ['assigned_operator_id' => $operator->id]);

    $this->actingAs($customer)->get(route('orders.cancellation-whatsapp', $order))->assertNotFound();

    $this->post(route('orders.cancellation-request', $order), ['reason' => 'Jadwal perjalanan berubah.']);

    $this->get(route('orders.cancellation-whatsapp', $order))->assertRedirectContains('https://wa.me/6281234567890?text=');

    $this->actingAs(User::factory()->create())->get(route('orders.cancellation-whatsapp', $order))->assertNotFound();
});
