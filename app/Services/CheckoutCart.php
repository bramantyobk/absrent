<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Ringkasan pesanan di sesi. Satu pesanan = satu periode (waktu mulai dan durasi) untuk semua kendaraannya, dengan
 * kendaraan maksimal sesuai `rental.max_vehicles_per_order`.
 */
class CheckoutCart
{
    public const PERIOD_KEY = 'checkout.period';

    public const VEHICLES_KEY = 'checkout.vehicles';

    public function __construct(private RentalQuote $rentalQuote, private Session $session) {}

    /**
     * @return array{start_at: string, days: int}|null
     */
    public function period(): ?array
    {
        return $this->session->get(self::PERIOD_KEY);
    }

    /**
     * @return list<int>
     */
    public function vehicleIds(): array
    {
        return array_values($this->session->get(self::VEHICLES_KEY, []));
    }

    public function count(): int
    {
        return count($this->vehicleIds());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function canAddMore(): bool
    {
        return ! $this->isEmpty() && $this->count() < config('rental.max_vehicles_per_order');
    }

    /**
     * Jadwal hanya dapat diubah saat pesanan berisi satu kendaraan. Dengan dua kendaraan, semuanya dihapus dulu.
     */
    public function canReschedule(): bool
    {
        return $this->count() === 1;
    }

    /**
     * Menambah kendaraan setelah aturan durasi dan ketersediaan lolos. Kendaraan tambahan harus memakai periode
     * kendaraan pertama. Ketersediaan diperiksa lagi (dengan penguncian) saat pesanan dibuat.
     *
     * @throws ValidationException
     */
    public function add(Vehicle $vehicle, string $startAt, int $days): void
    {
        $limit = config('rental.max_vehicles_per_order');

        if (in_array($vehicle->getKey(), $this->vehicleIds(), true)) {
            throw ValidationException::withMessages(['start_at' => 'Kendaraan ini sudah ada di pesanan Anda.']);
        }

        if ($this->count() >= $limit) {
            throw ValidationException::withMessages(['start_at' => "Satu pesanan maksimal {$limit} kendaraan."]);
        }

        $quote = $this->rentalQuote->calculate($vehicle, $startAt, $days);
        $this->rentalQuote->ensureAvailable($vehicle, $quote['start_at'], $quote['end_at']);

        $period = $this->period();

        if ($period !== null && ($period['days'] !== $days || ! RentalQuote::parse($period['start_at'])->equalTo($quote['start_at']))) {
            throw ValidationException::withMessages(['start_at' => 'Kendaraan tambahan harus memakai jadwal yang sama dengan pesanan ini.']);
        }

        $this->session->put(self::PERIOD_KEY, ['start_at' => $quote['start_at']->toDateTimeString(), 'days' => $days]);
        $this->session->put(self::VEHICLES_KEY, [...$this->vehicleIds(), $vehicle->getKey()]);
    }

    public function remove(int $index): void
    {
        $vehicleIds = $this->vehicleIds();
        unset($vehicleIds[$index]);

        $this->session->put(self::VEHICLES_KEY, array_values($vehicleIds));

        if ($vehicleIds === []) {
            $this->session->forget(self::PERIOD_KEY);
        }
    }

    /**
     * Mengosongkan pesanan yang berisi satu kendaraan dan mengembalikan periodenya agar dapat dipilih ulang.
     *
     * @return array{vehicle_id: int, start_at: string, days: int}
     */
    public function reschedule(): array
    {
        $period = $this->period();
        $vehicleId = $this->vehicleIds()[0];

        $this->clear();

        return ['vehicle_id' => $vehicleId] + $period;
    }

    public function clear(): void
    {
        $this->session->forget([self::PERIOD_KEY, self::VEHICLES_KEY]);
    }

    /**
     * Isi pesanan untuk ditampilkan: kendaraan, hari, subtotal, dan pesan bila item tidak lagi valid.
     *
     * @return Collection<int, array{index: int, vehicle: Vehicle, start_at: Carbon, end_at: Carbon, days: int, subtotal: int, error: ?string}>
     */
    public function lines(): Collection
    {
        $period = $this->period();

        if ($period === null) {
            return collect();
        }

        $vehicleIds = $this->vehicleIds();
        $vehicles = Vehicle::query()->whereIn('id', $vehicleIds)->get()->keyBy('id');

        return collect($vehicleIds)->map(function (int $vehicleId, int $index) use ($vehicles, $period): ?array {
            $vehicle = $vehicles->get($vehicleId);

            if (! $vehicle) {
                return null;
            }

            $start = RentalQuote::parse($period['start_at']);

            try {
                $quote = $this->rentalQuote->calculate($vehicle, $start, $period['days']);
                $this->rentalQuote->ensureAvailable($vehicle, $quote['start_at'], $quote['end_at']);
                $error = null;
            } catch (ValidationException $exception) {
                $quote = null;
                $error = collect($exception->errors())->flatten()->first();
            }

            return [
                'index' => $index,
                'vehicle' => $vehicle,
                'start_at' => $start,
                'end_at' => $start->copy()->addDays($period['days']),
                'days' => $period['days'],
                'subtotal' => $quote['subtotal'] ?? 0,
                'error' => $error,
            ];
        })->filter()->values();
    }
}
