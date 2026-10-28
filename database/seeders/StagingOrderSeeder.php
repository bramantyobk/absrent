<?php

namespace Database\Seeders;

use App\InspectionType;
use App\Models\BankAccount;
use App\Models\EquipmentItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\OrderStatus;
use App\TransactionStatus;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data pesanan contoh untuk hosting/staging agar grafik dashboard terisi. Tidak dipanggil dari DatabaseSeeder:
 * jalankan hanya dengan `php artisan db:seed --class=StagingOrderSeeder --force`.
 *
 * Tanpa Faker/factory karena paket dev tidak terpasang di hosting. Memakai kendaraan, perlengkapan, dan rekening
 * yang sudah ada serta menghindari jadwal pesanan yang sudah ada; pelanggan contoh dibuat bila belum ada. Pesanan ditandai kode berawalan `ABS-DM`.
 */
class StagingOrderSeeder extends Seeder
{
    private const CODE_PREFIX = 'ABS-DM';

    /**
     * @var Collection<int, Vehicle>
     */
    private Collection $vehicles;

    /**
     * @var Collection<int, User>
     */
    private Collection $customers;

    private User $staff;

    /**
     * @var Collection<int, BankAccount>
     */
    private Collection $bankAccounts;

    /**
     * Jadwal yang sudah menahan unit, per vehicle id.
     *
     * @var array<int, list<array{0: Carbon, 1: Carbon}>>
     */
    private array $booked = [];

    /**
     * Odometer berjalan per vehicle id, ditulis ke kendaraan di akhir.
     *
     * @var array<int, int>
     */
    private array $odometer = [];

    /**
     * @var array<string, Collection<int, EquipmentItem>>
     */
    private array $equipment = [];

    private int $sequence = 0;

    public function run(): void
    {
        if (Order::query()->where('code', 'like', self::CODE_PREFIX.'%')->exists()) {
            $this->command?->warn('Pesanan contoh sudah ada, seeder dilewati.');

            return;
        }

        $this->vehicles = Vehicle::query()->where('is_available', true)->get();

        if ($this->vehicles->count() < 2) {
            throw new RuntimeException('Tambahkan minimal 2 kendaraan yang tampil di katalog sebelum menjalankan seeder ini.');
        }

        mt_srand(2026);

        $this->staff = User::query()->where('role', UserRole::Operator)->first()
            ?? User::query()->where('role', UserRole::Admin)->firstOrFail();
        $this->bankAccounts = BankAccount::query()->where('is_active', true)->get();

        OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query->whereIn('status', OrderStatus::holdingSchedule()))
            ->get(['vehicle_id', 'start_at', 'end_at'])
            ->each(fn (OrderItem $item) => $this->booked[$item->vehicle_id][] = [$item->start_at, $item->end_at]);

        $this->customers = $this->customers();

        DB::transaction(function (): void {
            $this->seedCompleted();
            $this->seedRented();
            $this->seedBooking();
            $this->seedOthers();

            foreach ($this->odometer as $vehicleId => $km) {
                Vehicle::query()->whereKey($vehicleId)->update(['odometer_km' => $km]);
            }
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function customers(): Collection
    {
        $names = ['Andi Pratama', 'Siti Rahma', 'Budi Santoso', 'Dewi Lestari', 'Rizky Hidayat'];

        return collect($names)->map(fn (string $name, int $index): User => User::query()->firstOrCreate(
            ['email' => 'pelanggan'.($index + 1).'@demo.absrent.test'],
            [
                'name' => $name,
                'phone' => '62812000010'.($index + 1),
                'password' => Hash::make(Str::random(32)),
                'role' => UserRole::User,
                'is_active' => true,
            ],
        ));
    }

    private function seedCompleted(): void
    {
        for ($offset = -28; $offset <= -6; $offset++) {
            $orders = $offset % 3 === 0 ? 2 : 1;

            for ($i = 0; $i < $orders; $i++) {
                $this->createOrder(OrderStatus::Completed, TransactionStatus::Paid, now()->startOfDay()->addDays($offset)->setTime(8, 0), mt_rand(1, 3), withPickup: true, withReturn: true);
            }
        }
    }

    private function seedRented(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->createOrder(OrderStatus::Rented, TransactionStatus::Paid, now()->startOfDay()->subDays(2)->setTime(8, 0), 3, withPickup: true);
        }
    }

    private function seedBooking(): void
    {
        foreach ([2, 4, 7, 11] as $offset) {
            $this->createOrder(OrderStatus::Booking, TransactionStatus::Paid, now()->startOfDay()->addDays($offset)->setTime(8, 0), mt_rand(1, 3));
        }
    }

    private function seedOthers(): void
    {
        $start = fn (int $offset): Carbon => now()->startOfDay()->addDays($offset)->setTime(8, 0);

        $this->createOrder(OrderStatus::Pending, TransactionStatus::Verifying, $start(5), 2);
        $this->createOrder(OrderStatus::Pending, TransactionStatus::Pending, $start(8), 2);
        $this->createOrder(OrderStatus::Cancelled, TransactionStatus::Cancelled, $start(-9), 2, blocks: false);
        $this->createOrder(OrderStatus::Failed, TransactionStatus::Failed, $start(-4), 1, blocks: false);
    }

    private function createOrder(OrderStatus $status, TransactionStatus $transactionStatus, Carbon $start, int $days, bool $withPickup = false, bool $withReturn = false, bool $blocks = true): void
    {
        $end = $start->copy()->addDays($days);
        $vehicle = $this->freeVehicle($start, $end, $blocks);

        if ($vehicle === null) {
            return;
        }

        $createdAt = $start->copy()->subDay();

        if ($createdAt->isFuture()) {
            $createdAt = now()->subHours(mt_rand(2, 20));
        }

        $hasProof = $transactionStatus !== TransactionStatus::Pending && $transactionStatus !== TransactionStatus::Cancelled;
        $confirmedAt = $hasProof ? $this->notFuture($createdAt->copy()->addHour()) : null;
        $verifiedAt = in_array($transactionStatus, [TransactionStatus::Paid, TransactionStatus::Failed], true)
            ? $this->notFuture($createdAt->copy()->addHours(3))
            : null;
        $total = $days * $vehicle->price_per_day;

        $order = Order::query()->forceCreate([
            'code' => self::CODE_PREFIX.str_pad((string) ++$this->sequence, 6, '0', STR_PAD_LEFT),
            'user_id' => $this->customers->random()->id,
            'assigned_operator_id' => $hasProof ? $this->staff->id : null,
            'created_by' => null,
            'total_price' => $total,
            'status' => $status,
            'expires_at' => $status === OrderStatus::Pending ? ($confirmedAt ?? $createdAt)->copy()->addDay() : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $item = $order->items()->forceCreate([
            'order_id' => $order->id,
            'vehicle_id' => $vehicle->id,
            'start_at' => $start,
            'end_at' => $end,
            'days' => $days,
            'unit_price' => $vehicle->price_per_day,
            'subtotal' => $total,
        ]);

        Transaction::query()->forceCreate([
            'order_id' => $order->id,
            'bank_account_id' => $hasProof ? $this->bankAccounts->shuffle()->first()?->id : null,
            'amount' => $total,
            'confirmed_at' => $confirmedAt,
            'status' => $transactionStatus,
            'note' => $transactionStatus === TransactionStatus::Failed ? 'Bukti transfer tidak sesuai dengan nominal pesanan.' : null,
            'verified_by' => $verifiedAt ? $this->staff->id : null,
            'verified_at' => $verifiedAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->odometer[$vehicle->id] ??= $vehicle->odometer_km ?? mt_rand(3000, 15000);

        if ($withPickup) {
            $this->inspect($item->id, $vehicle, InspectionType::Pickup, $start, 100);
        }

        if ($withReturn) {
            $this->odometer[$vehicle->id] += $days * mt_rand(40, 120);
            $this->inspect($item->id, $vehicle, InspectionType::Return, $end->copy()->addHours(mt_rand(0, 3)), mt_rand(30, 90));
        }
    }

    private function inspect(int $orderItemId, Vehicle $vehicle, InspectionType $type, Carbon $at, int $energyLevel): void
    {
        $equipment = $this->equipment[$vehicle->type->value] ??= EquipmentItem::query()->active()->forCategory($vehicle->type)->ordered()->get();
        $missing = $type === InspectionType::Return && mt_rand(1, 6) === 1;

        VehicleInspection::query()->forceCreate([
            'vehicle_id' => $vehicle->id,
            'order_item_id' => $orderItemId,
            'type' => $type,
            'odometer_km' => $this->odometer[$vehicle->id],
            'energy_level' => $energyLevel,
            'equipment_checks' => $equipment->values()->map(fn (EquipmentItem $item, int $index): array => [
                'equipment_item_id' => $item->id,
                'name' => $item->name,
                'expected' => $item->quantity,
                'checked' => $missing && $index === 0 ? max(0, $item->quantity - 1) : $item->quantity,
            ])->all(),
            'note' => $missing ? 'Ada perlengkapan yang kurang saat dikembalikan.' : null,
            'inspected_by' => $this->staff->id,
            'inspected_at' => $at,
        ]);
    }

    /**
     * Unit yang kosong pada rentang (dengan jeda satu hari di kedua sisi). Pesanan yang tidak menahan jadwal tidak dicatat.
     */
    private function freeVehicle(Carbon $start, Carbon $end, bool $blocks): ?Vehicle
    {
        foreach ($this->vehicles->shuffle() as $vehicle) {
            if ($blocks && $this->overlaps($vehicle->id, $start, $end)) {
                continue;
            }

            if ($blocks) {
                $this->booked[$vehicle->id][] = [$start, $end];
            }

            return $vehicle;
        }

        return null;
    }

    private function overlaps(int $vehicleId, Carbon $start, Carbon $end): bool
    {
        foreach ($this->booked[$vehicleId] ?? [] as [$bookedStart, $bookedEnd]) {
            if ($start < $bookedEnd->copy()->addDay() && $end->copy()->addDay() > $bookedStart) {
                return true;
            }
        }

        return false;
    }

    private function notFuture(Carbon $time): Carbon
    {
        return $time->isFuture() ? now() : $time;
    }
}
