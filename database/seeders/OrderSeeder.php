<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OrderSeeder extends Seeder
{
    /**
     * @var Collection<int, User>
     */
    private Collection $customers;

    /**
     * @var Collection<int, User>
     */
    private Collection $operators;

    /**
     * @var Collection<int, User>
     */
    private Collection $dutyOperators;

    /**
     * @var Collection<int, Vehicle>
     */
    private Collection $vehicles;

    /**
     * @var Collection<int, BankAccount>
     */
    private Collection $bankAccounts;

    /**
     * Jadwal kendaraan yang sudah menahan slot, dikelompokkan per vehicle id.
     *
     * @var array<int, list<array{0: Carbon, 1: Carbon}>>
     */
    private array $bookedWindows = [];

    private int $rotation = 0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->customers = User::query()->where('role', UserRole::User)->get();
        $this->operators = User::query()->where('role', UserRole::Operator)->get();
        $this->dutyOperators = $this->operators->where('is_on_duty', true)->values();
        $this->vehicles = Vehicle::query()->where('is_available', true)->get();
        $this->bankAccounts = BankAccount::query()->where('is_active', true)->get();

        foreach ($this->scenarios() as $scenario) {
            for ($i = 0; $i < $scenario['count']; $i++) {
                $this->createOrder($scenario);
            }
        }
    }

    /**
     * Skenario pesanan. Tanggal (offset hari dari hari ini) dipilih konsisten dengan statusnya.
     *
     * @return list<array{count: int, order: string, transaction: string, from: int, to: int, days: array{0: int, 1: int}, blocks: bool, cancellation?: string}>
     */
    private function scenarios(): array
    {
        return [
            ['count' => 10, 'order' => 'completed', 'transaction' => 'paid', 'from' => -40, 'to' => -8, 'days' => [1, 4], 'blocks' => true],
            ['count' => 3, 'order' => 'rented', 'transaction' => 'paid', 'from' => -2, 'to' => -1, 'days' => [3, 4], 'blocks' => true],
            ['count' => 4, 'order' => 'booking', 'transaction' => 'paid', 'from' => 2, 'to' => 14, 'days' => [1, 4], 'blocks' => true],
            ['count' => 1, 'order' => 'booking', 'transaction' => 'paid', 'from' => 5, 'to' => 10, 'days' => [2, 3], 'blocks' => true, 'cancellation' => 'pending'],
            ['count' => 1, 'order' => 'booking', 'transaction' => 'paid', 'from' => 5, 'to' => 10, 'days' => [2, 3], 'blocks' => true, 'cancellation' => 'rejected'],
            ['count' => 3, 'order' => 'pending', 'transaction' => 'verifying', 'from' => 3, 'to' => 14, 'days' => [1, 4], 'blocks' => true],
            ['count' => 3, 'order' => 'pending', 'transaction' => 'pending', 'from' => 3, 'to' => 14, 'days' => [1, 4], 'blocks' => true],
            ['count' => 2, 'order' => 'failed', 'transaction' => 'failed', 'from' => -10, 'to' => 10, 'days' => [1, 3], 'blocks' => false],
            ['count' => 2, 'order' => 'cancelled', 'transaction' => 'cancelled', 'from' => -10, 'to' => 10, 'days' => [1, 3], 'blocks' => false],
            ['count' => 1, 'order' => 'cancelled', 'transaction' => 'paid', 'from' => 3, 'to' => 10, 'days' => [1, 3], 'blocks' => false, 'cancellation' => 'approved'],
        ];
    }

    /**
     * @param  array{count: int, order: string, transaction: string, from: int, to: int, days: array{0: int, 1: int}, blocks: bool, cancellation?: string}  $scenario
     */
    private function createOrder(array $scenario): void
    {
        $customer = $this->customers->random();
        $slots = $this->pickSlots($scenario);
        $firstStart = $slots[0]['start'];

        $createdAt = $firstStart->copy()->subDay();
        $expiresAt = null;

        if ($scenario['order'] === 'pending') {
            $createdAt = now()->subHours(fake()->numberBetween(1, 20));
            $expiresAt = $createdAt->copy()->addDay();
        } elseif ($createdAt->isFuture()) {
            $createdAt = now()->subHours(fake()->numberBetween(1, 40));
        }

        $order = Order::factory()
            ->{$scenario['order']}()
            ->for($customer, 'user')
            ->create(array_filter([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'expires_at' => $expiresAt,
            ]));

        $total = 0;

        foreach ($slots as $slot) {
            $subtotal = $slot['days'] * $slot['vehicle']->price_per_day;
            $total += $subtotal;

            OrderItem::factory()->for($order, 'order')->for($slot['vehicle'], 'vehicle')->create([
                'start_at' => $slot['start'],
                'end_at' => $slot['end'],
                'days' => $slot['days'],
                'unit_price' => $slot['vehicle']->price_per_day,
                'subtotal' => $subtotal,
            ]);
        }

        $order->update(['total_price' => $total]);

        $this->createTransaction($order, $scenario, $total, $createdAt);

        if (isset($scenario['cancellation'])) {
            CancellationRequest::factory()
                ->{$scenario['cancellation']}()
                ->for($order, 'order')
                ->create(array_filter([
                    'user_id' => $customer->id,
                    'handled_by' => $scenario['cancellation'] === 'pending' ? null : $this->operators->random()->id,
                    'handled_at' => $scenario['cancellation'] === 'pending' ? null : $this->verifiedAt($createdAt),
                ]));
        }
    }

    /**
     * @param  array{count: int, order: string, transaction: string, from: int, to: int, days: array{0: int, 1: int}, blocks: bool, cancellation?: string}  $scenario
     */
    private function createTransaction(Order $order, array $scenario, int $total, Carbon $createdAt): void
    {
        $hasProof = in_array($scenario['transaction'], ['verifying', 'paid', 'failed'], true);
        $isVerified = in_array($scenario['transaction'], ['paid', 'failed'], true);

        $attributes = [
            'amount' => $total,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        if ($hasProof) {
            $attributes['bank_account_id'] = $this->bankAccounts->random()->id;
            $order->update([
                'assigned_operator_id' => $this->nextDutyOperator()->id,
            ]);
        }

        if ($isVerified) {
            $attributes['verified_by'] = $this->operators->random()->id;
            $attributes['verified_at'] = $this->verifiedAt($createdAt);
        }

        Transaction::factory()
            ->{$scenario['transaction']}()
            ->for($order, 'order')
            ->create($attributes);
    }

    /**
     * Pilih 1 atau 2 kendaraan (pesanan multi-kendaraan) dengan jadwal yang tidak bentrok.
     *
     * @param  array{count: int, order: string, transaction: string, from: int, to: int, days: array{0: int, 1: int}, blocks: bool, cancellation?: string}  $scenario
     * @return list<array{vehicle: Vehicle, start: Carbon, end: Carbon, days: int}>
     */
    private function pickSlots(array $scenario): array
    {
        $slots = [];
        $usedVehicleIds = [];
        $itemCount = fake()->boolean(25) ? 2 : 1;

        for ($i = 0; $i < $itemCount; $i++) {
            $slot = $this->findFreeSlot($scenario, $usedVehicleIds);
            $usedVehicleIds[] = $slot['vehicle']->id;
            $slots[] = $slot;
        }

        return $slots;
    }

    /**
     * @param  array{count: int, order: string, transaction: string, from: int, to: int, days: array{0: int, 1: int}, blocks: bool, cancellation?: string}  $scenario
     * @param  list<int>  $excludedVehicleIds
     * @return array{vehicle: Vehicle, start: Carbon, end: Carbon, days: int}
     */
    private function findFreeSlot(array $scenario, array $excludedVehicleIds): array
    {
        $candidates = $this->vehicles->whereNotIn('id', $excludedVehicleIds);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $vehicle = $candidates->random();
            $days = fake()->numberBetween($scenario['days'][0], $scenario['days'][1]);
            $start = now()->startOfDay()->addDays(fake()->numberBetween($scenario['from'], $scenario['to']))->setTime(8, 0);
            $end = $start->copy()->addDays($days);

            if (! $scenario['blocks'] || ! $this->overlaps($vehicle->id, $start, $end)) {
                if ($scenario['blocks']) {
                    $this->bookedWindows[$vehicle->id][] = [$start, $end];
                }

                return ['vehicle' => $vehicle, 'start' => $start, 'end' => $end, 'days' => $days];
            }
        }

        throw new \RuntimeException('Tidak menemukan jadwal kendaraan yang kosong untuk seeder.');
    }

    private function overlaps(int $vehicleId, Carbon $start, Carbon $end): bool
    {
        foreach ($this->bookedWindows[$vehicleId] ?? [] as [$windowStart, $windowEnd]) {
            if ($start < $windowEnd && $end > $windowStart) {
                return true;
            }
        }

        return false;
    }

    private function nextDutyOperator(): User
    {
        return $this->dutyOperators[$this->rotation++ % $this->dutyOperators->count()];
    }

    private function verifiedAt(Carbon $createdAt): Carbon
    {
        $verifiedAt = $createdAt->copy()->addHours(fake()->numberBetween(1, 5));

        return $verifiedAt->isFuture() ? now() : $verifiedAt;
    }
}
