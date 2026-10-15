<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\Services\RentalQuote;
use App\TransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    public function __construct(private RentalQuote $rentalQuote) {}

    /**
     * Membuat pesanan `Pending` beserta item dan transaksi `Pending` untuk pelanggan. Dipakai bersama checkout
     * Blade dan API. Dengan `$idempotencyKey`, permintaan ulang yang sama mengembalikan pesanan yang sudah dibuat
     * (`wasRecentlyCreated` bernilai false) dan tidak membuat pesanan ganda.
     *
     * @param  list<array{vehicle_id: int|string, start_at: string, end_at: string}>  $items
     *
     * @throws ValidationException
     */
    public function handle(User $customer, array $items, ?string $idempotencyKey = null): Order
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $this->create($customer, $items);
        }

        $cacheKey = "checkout:{$customer->getKey()}:{$idempotencyKey}";

        return Cache::lock("{$cacheKey}:lock", 10)->block(5, function () use ($customer, $items, $cacheKey): Order {
            $existing = ($orderId = Cache::get($cacheKey)) ? Order::query()->with(['items.vehicle', 'transaction'])->find($orderId) : null;

            if ($existing) {
                return $existing;
            }

            $order = $this->create($customer, $items);
            Cache::put($cacheKey, $order->getKey(), now()->addDay());

            return $order;
        });
    }

    /**
     * Baris kendaraan dikunci (urut id, mencegah deadlock) selama pengecekan bentrok agar tidak ada pesanan
     * atau servis yang menyelip.
     *
     * @param  list<array{vehicle_id: int|string, start_at: string, end_at: string}>  $items
     */
    private function create(User $customer, array $items): Order
    {
        return DB::transaction(function () use ($customer, $items): Order {
            $vehicles = Vehicle::query()
                ->whereIn('id', collect($items)->pluck('vehicle_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];

            foreach (array_values($items) as $index => $item) {
                $prefix = "items.{$index}.";
                $vehicle = $vehicles->get((int) $item['vehicle_id']);

                if (! $vehicle) {
                    throw ValidationException::withMessages(["{$prefix}vehicle_id" => 'Kendaraan tidak ditemukan.']);
                }

                $quote = $this->rentalQuote->calculate($vehicle, $item['start_at'], $item['end_at'], $prefix);
                $this->rentalQuote->ensureAvailable($vehicle, $quote['start_at'], $quote['end_at'], $prefix);
                $this->ensureNoOverlapWithin($lines, $vehicle, $quote, $prefix);

                $lines[] = ['vehicle' => $vehicle] + $quote;
            }

            $order = Order::create([
                'code' => Order::generateCode(),
                'user_id' => $customer->getKey(),
                'total_price' => array_sum(array_column($lines, 'subtotal')),
                'status' => OrderStatus::Pending,
                'expires_at' => now()->addHours(config('rental.pending_expiry_hours')),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'vehicle_id' => $line['vehicle']->getKey(),
                    'start_at' => $line['start_at'],
                    'end_at' => $line['end_at'],
                    'days' => $line['days'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                ]);
            }

            $order->transaction()->create([
                'amount' => $order->total_price,
                'status' => TransactionStatus::Pending,
            ]);

            return $order->load(['items.vehicle', 'transaction']);
        });
    }

    /**
     * Dua item untuk kendaraan yang sama dalam satu pesanan tidak boleh saling menahan (termasuk jeda pengembalian).
     *
     * @param  list<array{vehicle: Vehicle, start_at: Carbon, end_at: Carbon}>  $lines
     * @param  array{start_at: Carbon, end_at: Carbon}  $quote
     *
     * @throws ValidationException
     */
    private function ensureNoOverlapWithin(array $lines, Vehicle $vehicle, array $quote, string $prefix): void
    {
        $buffer = config('rental.return_buffer_hours');

        foreach ($lines as $line) {
            if ($line['vehicle']->isNot($vehicle)) {
                continue;
            }

            [$first, $second] = $line['start_at']->lte($quote['start_at']) ? [$line, $quote] : [$quote, $line];

            if ($second['start_at']->lt($first['end_at']->copy()->addHours($buffer))) {
                throw ValidationException::withMessages([
                    "{$prefix}start_at" => 'Rentang ini bertabrakan dengan item lain untuk kendaraan yang sama pada pesanan ini.',
                ]);
            }
        }
    }
}
