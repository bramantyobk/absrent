<?php

namespace App\Services;

use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RentalQuote
{
    /**
     * Menghitung hari dan subtotal satu item sewa, sekaligus menolak jadwal yang melanggar aturan durasi:
     * mulai tidak di masa lalu, durasi minimal 24 jam, dan maksimal sesuai batas kendaraan (bawaan 7 hari).
     * Jumlah hari = durasi dibulatkan ke atas per 24 jam.
     *
     * @param  string  $fieldPrefix  awalan kunci error, mis. "items.0." untuk checkout multi-kendaraan
     * @return array{start_at: Carbon, end_at: Carbon, days: int, unit_price: int, subtotal: int}
     *
     * @throws ValidationException
     */
    public function calculate(Vehicle $vehicle, string|CarbonInterface $startAt, string|CarbonInterface $endAt, string $fieldPrefix = ''): array
    {
        $start = Carbon::parse($startAt);
        $end = Carbon::parse($endAt);
        $minimumHours = config('rental.min_rental_hours');
        $maximumDays = $vehicle->effectiveMaxRentalDays();
        $seconds = $end->getTimestamp() - $start->getTimestamp();

        if ($start->lt(now()->startOfMinute())) {
            $this->reject("{$fieldPrefix}start_at", 'Waktu mulai tidak boleh di masa lalu.');
        }

        if ($seconds < $minimumHours * 3600) {
            $this->reject("{$fieldPrefix}end_at", "Durasi sewa minimal {$minimumHours} jam.");
        }

        if ($seconds > $maximumDays * 86400) {
            $this->reject("{$fieldPrefix}end_at", "Durasi sewa maksimal {$maximumDays} hari untuk kendaraan ini.");
        }

        $days = (int) ceil($seconds / 86400);

        return [
            'start_at' => $start,
            'end_at' => $end,
            'days' => $days,
            'unit_price' => $vehicle->price_per_day,
            'subtotal' => $days * $vehicle->price_per_day,
        ];
    }

    /**
     * Memastikan unit tampil di katalog dan kosong pada rentang itu (pesanan aktif dengan jeda, dan jadwal servis).
     *
     * @throws ValidationException
     */
    public function ensureAvailable(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end, string $fieldPrefix = ''): void
    {
        $isAvailable = Vehicle::query()
            ->availableBetween($start, $end)
            ->whereKey($vehicle->getKey())
            ->exists();

        if (! $isAvailable) {
            $this->reject("{$fieldPrefix}start_at", 'Kendaraan tidak tersedia pada rentang waktu ini.');
        }
    }

    /**
     * @throws ValidationException
     */
    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
