<?php

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Membuat pesanan berstatus tertentu yang menahan kendaraan pada rentang waktu yang diberikan.
 */
function holdVehicle(Vehicle $vehicle, OrderStatus $status, string $startAt, string $endAt): OrderItem
{
    return OrderItem::factory()
        ->for(Order::factory()->create(['status' => $status]), 'order')
        ->for($vehicle, 'vehicle')
        ->create(['start_at' => $startAt, 'end_at' => $endAt]);
}

/**
 * Pesanan beserta satu item dan transaksi pada status tertentu (default: Pending dengan transaksi Menunggu Verifikasi).
 *
 * @param  array<string, mixed>  $orderAttributes
 * @param  array<string, mixed>  $transactionAttributes
 */
function orderWithTransaction(array $orderAttributes = [], array $transactionAttributes = [], ?Vehicle $vehicle = null): Order
{
    $order = Order::factory()->create($orderAttributes + ['status' => OrderStatus::Pending, 'total_price' => 300000, 'expires_at' => now()->addHours(20)]);
    OrderItem::factory()->for($order, 'order')->for($vehicle ?? Vehicle::factory()->create(), 'vehicle')->create();
    Transaction::factory()->for($order)->create($transactionAttributes + [
        'status' => TransactionStatus::Verifying,
        'bank_account_id' => BankAccount::factory(),
        'confirmed_at' => now()->subHour(),
    ]);

    return $order->load(['items.vehicle', 'transaction']);
}

/**
 * Isian inspeksi untuk setiap item pesanan (menurut id item), tanpa ceklis perlengkapan kecuali diberikan.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<int, array<string, mixed>>
 */
function inspectionPayload(Order $order, array $overrides = []): array
{
    return $order->items->mapWithKeys(fn (OrderItem $item): array => [$item->id => $overrides + ['odometer_km' => 500000, 'energy_level' => 80]])->all();
}
