<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\Services\RentalQuote;
use App\TransactionStatus;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateOrder
{
    public function __construct(private RentalQuote $rentalQuote) {}

    /**
     * Membuat pesanan `Pending` beserta item dan transaksi `Pending` untuk pelanggan. Dipakai bersama checkout
     * Blade dan API. Satu pesanan memakai satu periode (waktu mulai + jumlah hari) untuk semua kendaraannya.
     * Dengan `$idempotencyKey`, permintaan ulang yang sama mengembalikan pesanan yang sudah dibuat
     * (`wasRecentlyCreated` bernilai false) dan tidak membuat pesanan ganda.
     *
     * @param  list<int|string>  $vehicleIds
     *
     * @throws ValidationException
     */
    public function handle(User $customer, string $startAt, int $days, array $vehicleIds, ?string $idempotencyKey = null): Order
    {
        $this->ensureVehicleCount($vehicleIds);

        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $this->create($customer, $startAt, $days, $vehicleIds);
        }

        $cacheKey = "checkout:{$customer->getKey()}:{$idempotencyKey}";

        try {
            return Cache::lock("{$cacheKey}:lock", 10)->block(config('rental.checkout_lock_wait_seconds'), function () use ($customer, $startAt, $days, $vehicleIds, $cacheKey): Order {
                $existing = ($orderId = Cache::get($cacheKey)) ? Order::query()->with(['items.vehicle', 'transaction'])->find($orderId) : null;

                if ($existing) {
                    return $existing;
                }

                $order = $this->create($customer, $startAt, $days, $vehicleIds);
                Cache::put($cacheKey, $order->getKey(), now()->addDay());

                return $order;
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Permintaan dengan kunci yang sama sedang diproses. Coba lagi sebentar lagi.',
            ]);
        }
    }

    /**
     * Pesanan manual (booking langsung): aturan bentrok, jeda, dan durasi sama dengan pesanan online, tetapi pembayaran dicatat
     * staf di tempat sebagai Lunas sehingga pesanan langsung `Booking` tanpa masa kedaluwarsa dan tanpa batas pesanan Pending.
     * `created_by` diisi staf pembuat, `verified_by`/`verified_at` pada transaksi juga staf. Bukti dan rekening opsional.
     *
     * @param  list<int|string>  $vehicleIds
     * @param  array{bank_account_id?: ?int, proof_path?: ?string, note?: ?string}  $payment
     *
     * @throws ValidationException
     */
    public function handleForStaff(User $customer, User $staff, string $startAt, int $days, array $vehicleIds, array $payment = []): Order
    {
        $this->ensureVehicleCount($vehicleIds);

        return $this->create($customer, $startAt, $days, $vehicleIds, $staff, $payment);
    }

    /**
     * Baris kendaraan dikunci (urut id, mencegah deadlock) selama pengecekan bentrok agar tidak ada pesanan
     * atau servis yang menyelip.
     *
     * @param  list<int|string>  $vehicleIds
     * @param  array{bank_account_id?: ?int, proof_path?: ?string, note?: ?string}  $payment
     */
    private function create(User $customer, string $startAt, int $days, array $vehicleIds, ?User $staff = null, array $payment = []): Order
    {
        return DB::transaction(function () use ($customer, $startAt, $days, $vehicleIds, $staff, $payment): Order {
            if ($staff === null) {
                $this->ensureBelowPendingLimit($customer);
            }

            $vehicles = Vehicle::query()
                ->whereIn('id', $vehicleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];

            foreach (array_values($vehicleIds) as $index => $vehicleId) {
                $key = "items.{$index}.vehicle_id";
                $vehicle = $vehicles->get((int) $vehicleId);

                if (! $vehicle) {
                    throw ValidationException::withMessages([$key => 'Kendaraan tidak ditemukan.']);
                }

                $quote = $this->rentalQuote->calculate($vehicle, $startAt, $days, $key);
                $this->rentalQuote->ensureAvailable($vehicle, $quote['start_at'], $quote['end_at'], $key);

                $lines[] = ['vehicle' => $vehicle] + $quote;
            }

            $order = retry(3, fn (): Order => Order::create([
                'code' => Order::generateCode(),
                'user_id' => $customer->getKey(),
                'created_by' => $staff?->getKey() ?? $customer->getKey(),
                'total_price' => array_sum(array_column($lines, 'subtotal')),
                'status' => $staff ? OrderStatus::Booking : OrderStatus::Pending,
                'expires_at' => $staff ? null : now()->addHours(config('rental.pending_expiry_hours')),
            ]), 0, fn (Throwable $exception): bool => $exception instanceof UniqueConstraintViolationException);

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
                'status' => $staff ? TransactionStatus::Paid : TransactionStatus::Pending,
                'bank_account_id' => $payment['bank_account_id'] ?? null,
                'proof_path' => $payment['proof_path'] ?? null,
                'note' => $payment['note'] ?? null,
                'confirmed_at' => $staff ? now() : null,
                'verified_by' => $staff?->getKey(),
                'verified_at' => $staff ? now() : null,
            ]);

            return $order->load(['items.vehicle', 'transaction']);
        });
    }

    /**
     * @param  list<int|string>  $vehicleIds
     *
     * @throws ValidationException
     */
    private function ensureVehicleCount(array $vehicleIds): void
    {
        $limit = config('rental.max_vehicles_per_order');

        if ($vehicleIds === [] || count($vehicleIds) > $limit) {
            throw ValidationException::withMessages(['items' => "Satu pesanan berisi 1 sampai {$limit} kendaraan."]);
        }

        if (count(array_unique($vehicleIds)) !== count($vehicleIds)) {
            throw ValidationException::withMessages(['items' => 'Kendaraan yang sama tidak dapat dipilih dua kali dalam satu pesanan.']);
        }
    }

    /**
     * Pelanggan yang masih memiliki terlalu banyak pesanan Pending tidak dapat membuat pesanan baru. Baris pelanggan
     * dikunci agar dua permintaan bersamaan tidak sama-sama lolos.
     *
     * @throws ValidationException
     */
    private function ensureBelowPendingLimit(User $customer): void
    {
        User::query()->whereKey($customer->getKey())->lockForUpdate()->first();

        $limit = config('rental.max_pending_orders');
        $pending = Order::query()->whereBelongsTo($customer)->where('status', OrderStatus::Pending)->count();

        if ($pending >= $limit) {
            throw ValidationException::withMessages([
                'items' => "Anda masih memiliki {$pending} pesanan yang belum dikonfirmasi (maksimal {$limit}). Selesaikan atau batalkan pesanan tersebut sebelum membuat pesanan baru.",
            ]);
        }
    }
}
