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

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function payableOrder(User $customer, array $overrides = [], ?TransactionStatus $transactionStatus = null): Order
{
    $order = Order::factory()->for($customer)->create($overrides + ['code' => 'ABS-PAY00001', 'total_price' => 450000, 'expires_at' => now()->addDay()]);
    OrderItem::factory()->for($order, 'order')->for(Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']), 'vehicle')->create();
    Transaction::factory()->for($order)->create(['status' => $transactionStatus ?? TransactionStatus::Pending]);

    return $order;
}

function whatsAppTextOf(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return $query['text'];
}

it('requires a customer account for the payment routes', function (string $method, string $route) {
    $order = payableOrder(User::factory()->create());

    $this->{$method}(route($route, $order))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route, $order))->assertForbidden();
})->with([
    ['get', 'orders.payment'],
    ['post', 'orders.confirm'],
    ['get', 'orders.whatsapp'],
    ['post', 'orders.cancel'],
]);

it('sends the customer to the payment page after creating an order', function () {
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post(route('checkout.items.store', $vehicle), ['start_at' => '2026-10-14T09:00', 'days' => 2]);

    $this->post(route('checkout.store'))->assertRedirect(route('orders.payment', Order::sole()));
});

it('shows the total and only active bank accounts in order', function () {
    BankAccount::factory()->create(['bank_name' => 'Mandiri', 'account_number' => '3333333333', 'sort_order' => 2]);
    BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '1111111111', 'sort_order' => 1]);
    BankAccount::factory()->inactive()->create(['bank_name' => 'BRI', 'account_number' => '2222222222']);
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('orders.payment', payableOrder($customer)))
        ->assertOk()
        ->assertSee('Rp450.000')
        ->assertSeeInOrder(['BCA', '1111111111', 'Mandiri', '3333333333'])
        ->assertDontSee('2222222222');
});

it('warns and blocks confirmation when no bank account is active', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('orders.payment', payableOrder($customer)))
        ->assertSee('Rekening tujuan belum tersedia');
});

it('returns 404 on the payment page of another customer order', function () {
    $customer = User::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('orders.payment', payableOrder($customer)))->assertNotFound();
});

it('redirects the payment page of an order that is past the payment step to the order detail with a message', function (OrderStatus $status, TransactionStatus $transactionStatus, string $label) {
    $customer = User::factory()->create();
    $order = payableOrder($customer, ['status' => $status, 'code' => 'ABS-PAY00002'], $transactionStatus);

    $this->actingAs($customer)
        ->get(route('orders.payment', $order))
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', "Pesanan ABS-PAY00002 tidak lagi menunggu pembayaran (status: {$label}).");
})->with([
    'awaiting verification' => [OrderStatus::Pending, TransactionStatus::Verifying, 'Pending'],
    'booking' => [OrderStatus::Booking, TransactionStatus::Paid, 'Booking'],
    'cancelled' => [OrderStatus::Cancelled, TransactionStatus::Cancelled, 'Dibatalkan'],
    'failed' => [OrderStatus::Failed, TransactionStatus::Failed, 'Gagal'],
]);

it('opens WhatsApp in a new tab and moves the payment page to the order detail', function () {
    BankAccount::factory()->create();
    $customer = User::factory()->create();
    $order = payableOrder($customer);

    $this->actingAs($customer)
        ->get(route('orders.payment', $order))
        ->assertOk()
        ->assertSee('id="confirm-payment-form" target="_blank" data-payment-confirm', false)
        ->assertSee('data-after-url="'.route('orders.show', $order).'"', false);
});

it('opens the resend WhatsApp link in a new tab on the order detail', function () {
    $customer = User::factory()->create();
    $order = payableOrder($customer, ['code' => 'ABS-PAY00007'], TransactionStatus::Verifying);

    $this->actingAs($customer)
        ->get(route('orders.show', $order))
        ->assertSee('href="'.route('orders.whatsapp', $order).'" target="_blank" rel="noopener"', false);
});

it('confirms payment, moves the transaction to verifying, and redirects to the rotator operator on WhatsApp', function () {
    CompanyProfile::factory()->create(['support_whatsapp' => '6281200000000']);
    $bank = BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '1234567890']);
    $operator = User::factory()->operator()->onDuty()->create(['phone' => '6281311111111']);
    $customer = User::factory()->create(['name' => 'Budi Santoso']);
    $order = payableOrder($customer);

    $response = $this->actingAs($customer)->post(route('orders.confirm', $order), ['bank_account_id' => $bank->id]);

    $response->assertRedirectContains('https://wa.me/6281311111111?text=');
    expect(whatsAppTextOf($response->headers->get('Location')))
        ->toBe("Halo, saya Budi Santoso mau konfirmasi pembayaran:\nNomor Pesanan: ABS-PAY00001\nBank Transfer: BCA, 1234567890\nNominal Transfer: Rp450.000");

    $order->refresh()->load('transaction');

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->assigned_operator_id)->toBe($operator->id)
        ->and($order->expires_at->toDateTimeString())->toBe('2026-10-13 10:00:00')
        ->and($order->transaction->status)->toBe(TransactionStatus::Verifying)
        ->and($order->transaction->bank_account_id)->toBe($bank->id)
        ->and($order->transaction->confirmed_at->toDateTimeString())->toBe('2026-10-12 10:00:00')
        ->and($operator->refresh()->last_assigned_at->toDateTimeString())->toBe('2026-10-12 10:00:00');
});

it('rotates between on-duty operators by oldest assignment and skips the rest', function () {
    $bank = BankAccount::factory()->create();
    $never = User::factory()->operator()->onDuty()->create(['phone' => '6281300000001']);
    $oldest = User::factory()->operator()->onDuty()->create(['phone' => '6281300000002', 'last_assigned_at' => now()->subDays(2)]);
    $recent = User::factory()->operator()->onDuty()->create(['phone' => '6281300000003', 'last_assigned_at' => now()->subHour()]);
    User::factory()->operator()->create(['phone' => '6281300000004']);
    User::factory()->operator()->onDuty()->inactive()->create(['phone' => '6281300000005']);
    $customer = User::factory()->create();

    $assigned = collect(range(1, 4))->map(function (int $index) use ($customer, $bank): ?int {
        $order = payableOrder($customer, ['code' => "ABS-ROT0000{$index}"]);
        $this->actingAs($customer)->post(route('orders.confirm', $order), ['bank_account_id' => $bank->id]);
        $this->travel(1)->minute();

        return $order->refresh()->assigned_operator_id;
    });

    expect($assigned->all())->toBe([$never->id, $oldest->id, $recent->id, $never->id]);
});

it('falls back to the support number when no operator is on duty', function () {
    CompanyProfile::factory()->create(['support_whatsapp' => '6281200000000']);
    $bank = BankAccount::factory()->create();
    User::factory()->operator()->create();
    $customer = User::factory()->create();
    $order = payableOrder($customer);

    $this->actingAs($customer)
        ->post(route('orders.confirm', $order), ['bank_account_id' => $bank->id])
        ->assertRedirectContains('https://wa.me/6281200000000?text=');

    expect($order->refresh()->assigned_operator_id)->toBeNull();
});

it('rejects a missing, inactive, or unknown bank account without changing the order', function (?int $bankId) {
    $customer = User::factory()->create();
    $order = payableOrder($customer);
    $inactive = BankAccount::factory()->inactive()->create();

    $this->actingAs($customer)
        ->post(route('orders.confirm', $order), ['bank_account_id' => $bankId === 0 ? $inactive->id : $bankId])
        ->assertSessionHasErrors('bank_account_id');

    expect($order->refresh()->transaction->status)->toBe(TransactionStatus::Pending)
        ->and($order->transaction->confirmed_at)->toBeNull();
})->with([null, 0, 9999]);

it('allows confirming only once', function () {
    $bank = BankAccount::factory()->create();
    $customer = User::factory()->create();
    $order = payableOrder($customer);

    $this->actingAs($customer)->post(route('orders.confirm', $order), ['bank_account_id' => $bank->id]);
    $this->actingAs($customer)->post(route('orders.confirm', $order), ['bank_account_id' => $bank->id])->assertForbidden();
});

it('rejects confirmation after the payment deadline even if the scheduler has not run yet', function () {
    $bank = BankAccount::factory()->create();
    $customer = User::factory()->create();
    $order = payableOrder($customer, ['expires_at' => now()->subMinute()]);

    $this->actingAs($customer)
        ->post(route('orders.confirm', $order), ['bank_account_id' => $bank->id])
        ->assertSessionHasErrors('order');

    expect($order->refresh()->transaction->status)->toBe(TransactionStatus::Pending);
});

it('cannot confirm failed, cancelled, or another customer order', function () {
    $bank = BankAccount::factory()->create();
    $customer = User::factory()->create();
    $cancelled = payableOrder($customer, ['status' => OrderStatus::Cancelled, 'code' => 'ABS-PAY00003'], TransactionStatus::Cancelled);

    $this->actingAs($customer)->post(route('orders.confirm', $cancelled), ['bank_account_id' => $bank->id])->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('orders.confirm', payableOrder($customer, ['code' => 'ABS-PAY00004'])), ['bank_account_id' => $bank->id])->assertNotFound();
});

it('reopens the same WhatsApp link without changing the status', function () {
    $bank = BankAccount::factory()->create();
    User::factory()->operator()->onDuty()->create(['phone' => '6281311111111']);
    $customer = User::factory()->create();
    $order = payableOrder($customer);

    $first = $this->actingAs($customer)->post(route('orders.confirm', $order), ['bank_account_id' => $bank->id])->headers->get('Location');
    User::factory()->operator()->onDuty()->create(['phone' => '6281322222222']);

    $this->actingAs($customer)->get(route('orders.whatsapp', $order))->assertRedirect($first);

    expect($order->refresh()->transaction->status)->toBe(TransactionStatus::Verifying);
});

it('only reopens the WhatsApp link while the order awaits verification', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get(route('orders.whatsapp', payableOrder($customer)))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('orders.whatsapp', payableOrder($customer, ['code' => 'ABS-PAY00005'], TransactionStatus::Verifying)))->assertNotFound();
});

it('cancels an unconfirmed order, cancels its transaction, and releases the vehicle', function () {
    $customer = User::factory()->create();
    $order = payableOrder($customer);
    $item = $order->items()->first();

    $this->actingAs($customer)->post(route('orders.cancel', $order))->assertRedirect(route('orders.show', $order));

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->transaction->status)->toBe(TransactionStatus::Cancelled)
        ->and(Vehicle::query()->availableBetween($item->start_at, $item->end_at)->whereKey($item->vehicle_id)->exists())->toBeTrue();
});

it('cannot cancel directly once payment is confirmed', function () {
    $customer = User::factory()->create();
    $order = payableOrder($customer, [], TransactionStatus::Verifying);

    $this->actingAs($customer)->post(route('orders.cancel', $order))->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('shows pay and cancel actions on a pending order and the WhatsApp action while awaiting verification', function () {
    $customer = User::factory()->create();
    $pending = payableOrder($customer);
    $verifying = payableOrder($customer, ['code' => 'ABS-PAY00006'], TransactionStatus::Verifying);

    $this->actingAs($customer)->get(route('orders.show', $pending))->assertSee('Bayar sekarang')->assertSee('Batalkan pesanan')->assertDontSee('Kirim pesan konfirmasi');
    $this->actingAs($customer)->get(route('orders.show', $verifying))->assertDontSee('Bayar sekarang')->assertSee('Kirim pesan konfirmasi ke operator');
});
