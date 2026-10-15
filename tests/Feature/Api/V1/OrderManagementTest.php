<?php

use App\CancellationStatus;
use App\Models\CancellationRequest;
use App\Models\User;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
    Storage::fake('local');
});

it('rejects guests and customers on the staff order endpoints', function (string $method, string $route) {
    $order = orderWithTransaction();
    $parameter = str_contains($route, 'transactions') ? $order->transaction : (str_contains($route, 'cancellation') ? CancellationRequest::factory()->create() : $order);

    $this->{$method}(route($route, $parameter))->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->{$method}(route($route, $parameter))->assertForbidden();
})->with([
    ['getJson', 'api.v1.dashboard.orders.index'],
    ['getJson', 'api.v1.dashboard.orders.show'],
    ['patchJson', 'api.v1.dashboard.orders.status'],
    ['getJson', 'api.v1.dashboard.transactions.index'],
    ['postJson', 'api.v1.dashboard.transactions.verify'],
    ['getJson', 'api.v1.dashboard.transactions.proof'],
    ['getJson', 'api.v1.dashboard.cancellation-requests.index'],
    ['patchJson', 'api.v1.dashboard.cancellation-requests.update'],
]);

it('lists and filters orders for staff', function () {
    orderWithTransaction(['code' => 'ABS-APIONE01', 'user_id' => User::factory()->create(['name' => 'Budi Santoso'])]);
    orderWithTransaction(['code' => 'ABS-APITWO02', 'status' => OrderStatus::Booking]);
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->getJson(route('api.v1.dashboard.orders.index'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.code', 'ABS-APITWO02');
    $this->getJson(route('api.v1.dashboard.orders.index', ['search' => 'Budi']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer.name', 'Budi Santoso');
    $this->getJson(route('api.v1.dashboard.orders.index', ['status' => 'booking']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'ABS-APITWO02');
    $this->getJson(route('api.v1.dashboard.orders.index', ['status' => 'bogus']))->assertUnprocessable();
});

it('shows an order and changes its status through the api', function () {
    $order = orderWithTransaction(['status' => OrderStatus::Booking], ['status' => TransactionStatus::Paid]);
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->getJson(route('api.v1.dashboard.orders.show', $order))->assertOk()->assertJsonPath('data.customer.id', $order->user_id)->assertJsonPath('data.transaction.status', 'paid');
    $this->patchJson(route('api.v1.dashboard.orders.status', $order), ['status' => 'rented'])->assertOk()->assertJsonPath('data.status', 'rented');
    $this->patchJson(route('api.v1.dashboard.orders.status', $order), ['status' => 'rented'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->patchJson(route('api.v1.dashboard.orders.status', $order), ['status' => 'stolen'])->assertUnprocessable();
});

it('verifies a transaction as paid with a proof and exposes it only through the authorized endpoint', function () {
    $order = orderWithTransaction();
    Sanctum::actingAs(User::factory()->operator()->create());

    $response = $this->post(route('api.v1.dashboard.transactions.verify', $order->transaction), ['status' => 'paid', 'proof' => UploadedFile::fake()->image('bukti.png')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.proof_url', route('api.v1.dashboard.transactions.proof', $order->transaction));

    expect($order->refresh()->status)->toBe(OrderStatus::Booking)
        ->and($response->json('data.proof_url'))->not->toContain('payment-proofs');

    $this->get($response->json('data.proof_url'))->assertOk();
});

it('verifies a transaction as failed with a note and validates the payload', function () {
    $order = orderWithTransaction();
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->postJson(route('api.v1.dashboard.transactions.verify', $order->transaction), ['status' => 'paid'])->assertUnprocessable()->assertJsonValidationErrors('proof');
    $this->postJson(route('api.v1.dashboard.transactions.verify', $order->transaction), ['status' => 'failed'])->assertUnprocessable()->assertJsonValidationErrors('note');

    $this->postJson(route('api.v1.dashboard.transactions.verify', $order->transaction), ['status' => 'failed', 'note' => 'Dana tidak ditemukan.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.order_status', 'failed');

    $this->postJson(route('api.v1.dashboard.transactions.verify', $order->transaction), ['status' => 'failed', 'note' => 'Lagi.'])->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('lists transactions with the near expiry flag, verifying first', function () {
    orderWithTransaction(['code' => 'ABS-APIPAID1', 'status' => OrderStatus::Booking], ['status' => TransactionStatus::Paid]);
    orderWithTransaction(['code' => 'ABS-APISOON2', 'expires_at' => now()->addHours(2)]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.dashboard.transactions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.order_code', 'ABS-APISOON2')
        ->assertJsonPath('data.0.is_near_expiry', true)
        ->assertJsonPath('data.1.is_near_expiry', false);

    $this->getJson(route('api.v1.dashboard.transactions.show', orderWithTransaction()->transaction))->assertOk()->assertJsonPath('data.status', 'verifying');
});

it('lets a customer request cancellation and see the proof of their own order through the api', function () {
    $customer = User::factory()->create();
    $order = orderWithTransaction(['user_id' => $customer->id, 'status' => OrderStatus::Booking, 'expires_at' => null], ['status' => TransactionStatus::Paid, 'proof_path' => 'payment-proofs/bukti.jpg']);
    Storage::disk('local')->put('payment-proofs/bukti.jpg', 'isi-bukti');
    Sanctum::actingAs($customer);

    $this->getJson(route('api.v1.orders.proof', $order))->assertOk();
    $this->postJson(route('api.v1.orders.cancellation-request', $order), [])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(route('api.v1.orders.cancellation-request', $order), ['reason' => 'Jadwal perjalanan berubah.'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
    $this->postJson(route('api.v1.orders.cancellation-request', $order), ['reason' => 'Sekali lagi.'])->assertForbidden();

    $this->getJson(route('api.v1.orders.show', $order->load('cancellationRequests')))->assertJsonPath('data.transaction.has_proof', true);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson(route('api.v1.orders.proof', $order))->assertNotFound();
    $this->postJson(route('api.v1.orders.cancellation-request', $order), ['reason' => 'Bukan pesanan saya.'])->assertNotFound();
});

it('lists and decides cancellation requests through the api', function () {
    $order = orderWithTransaction(['status' => OrderStatus::Booking, 'expires_at' => null], ['status' => TransactionStatus::Paid]);
    $request = CancellationRequest::factory()->for($order)->create();
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->getJson(route('api.v1.dashboard.cancellation-requests.index', ['status' => 'pending']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_code', $order->code);

    $this->patchJson(route('api.v1.dashboard.cancellation-requests.update', $request), ['decision' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->patchJson(route('api.v1.dashboard.cancellation-requests.update', $request), ['decision' => 'approved', 'note' => 'Refund via BCA.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($order->refresh()->status)->toBe(OrderStatus::Refunded)->and($request->refresh()->status)->toBe(CancellationStatus::Approved);
});
