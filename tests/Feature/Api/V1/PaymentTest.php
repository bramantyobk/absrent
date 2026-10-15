<?php

use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;

function apiPayableOrder(User $customer, TransactionStatus $transactionStatus = TransactionStatus::Pending): Order
{
    $order = Order::factory()->for($customer)->create(['total_price' => 450000]);
    OrderItem::factory()->for($order, 'order')->for(Vehicle::factory()->create(), 'vehicle')->create();
    Transaction::factory()->for($order)->create(['status' => $transactionStatus]);

    return $order;
}

it('rejects guests and staff on the payment endpoints', function (string $method, string $route) {
    $order = apiPayableOrder(User::factory()->create());

    $this->{$method}(route($route, $order))->assertUnauthorized();
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route, $order))->assertForbidden();
})->with([
    ['getJson', 'api.v1.orders.payment'],
    ['postJson', 'api.v1.orders.confirm'],
    ['getJson', 'api.v1.orders.whatsapp'],
    ['postJson', 'api.v1.orders.cancel'],
]);

it('returns the total and active bank accounts for payment', function () {
    BankAccount::factory()->create(['bank_name' => 'BCA', 'sort_order' => 1]);
    BankAccount::factory()->inactive()->create(['bank_name' => 'BRI']);
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->getJson(route('api.v1.orders.payment', apiPayableOrder($customer)))
        ->assertOk()
        ->assertJsonPath('data.total_price', 450000)
        ->assertJsonCount(1, 'bank_accounts')
        ->assertJsonPath('bank_accounts.0.bank_name', 'BCA');
});

it('confirms payment and returns the WhatsApp url of the rotator operator', function () {
    $bank = BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '1234567890']);
    $operator = User::factory()->operator()->onDuty()->create(['phone' => '6281311111111']);
    $customer = User::factory()->create(['name' => 'Budi Santoso']);
    $order = apiPayableOrder($customer);

    $response = $this->actingAs($customer)
        ->postJson(route('api.v1.orders.confirm', $order), ['bank_account_id' => $bank->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.transaction.status', 'verifying')
        ->assertJsonPath('data.transaction.bank_account.bank_name', 'BCA')
        ->assertJsonStructure(['data' => ['transaction' => ['confirmed_at']], 'whatsapp_url']);

    expect($response->json('whatsapp_url'))->toStartWith('https://wa.me/6281311111111?text=')
        ->and(rawurldecode($response->json('whatsapp_url')))->toContain($order->code)->toContain('BCA, 1234567890')->toContain('Rp450.000')
        ->and($order->refresh()->assigned_operator_id)->toBe($operator->id);
});

it('falls back to the support number and validates the bank account', function () {
    CompanyProfile::factory()->create(['support_whatsapp' => '6281200000000']);
    $bank = BankAccount::factory()->create();
    $customer = User::factory()->create();
    $order = apiPayableOrder($customer);

    $this->actingAs($customer)->postJson(route('api.v1.orders.confirm', $order), [])->assertUnprocessable()->assertJsonValidationErrors('bank_account_id');

    $this->postJson(route('api.v1.orders.confirm', $order), ['bank_account_id' => $bank->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    expect($this->getJson(route('api.v1.orders.whatsapp', $order))->assertOk()->json('data.whatsapp_url'))->toStartWith('https://wa.me/6281200000000?text=');
});

it('returns 403 when confirming twice and 404 for another customer order', function () {
    $bank = BankAccount::factory()->create();
    $customer = User::factory()->create();
    $order = apiPayableOrder($customer);

    $this->actingAs($customer)->postJson(route('api.v1.orders.confirm', $order), ['bank_account_id' => $bank->id])->assertOk();
    $this->postJson(route('api.v1.orders.confirm', $order), ['bank_account_id' => $bank->id])->assertForbidden();

    $this->actingAs(User::factory()->create())->postJson(route('api.v1.orders.cancel', apiPayableOrder($customer)))->assertNotFound();
});

it('cancels an unconfirmed order but not a confirmed one', function () {
    $customer = User::factory()->create();
    $pending = apiPayableOrder($customer);
    $verifying = apiPayableOrder($customer, TransactionStatus::Verifying);

    $this->actingAs($customer)
        ->postJson(route('api.v1.orders.cancel', $pending))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.transaction.status', 'cancelled');

    $this->postJson(route('api.v1.orders.cancel', $verifying))->assertForbidden();

    expect($verifying->refresh()->status)->toBe(OrderStatus::Pending);
});
