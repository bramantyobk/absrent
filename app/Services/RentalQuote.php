<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Weekday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RentalQuote
{
    /**
     * Membaca waktu masukan sebagai waktu lokasi perusahaan (zona aplikasi). Masukan tanpa offset dibaca sebagai zona
     * aplikasi, sedangkan masukan dengan offset (mis. 09:00+08:00 atau Z) dikonversi ke zona aplikasi.
     */
    public static function parse(string|CarbonInterface $value): Carbon
    {
        $timezone = config('app.timezone');

        return Carbon::parse($value, $timezone)->setTimezone($timezone);
    }

    /**
     * Menghitung subtotal satu item sewa untuk periode (waktu mulai + jumlah hari) dan menolak yang melanggar aturan
     * durasi: mulai tidak di masa lalu, minimal 1 hari (kelipatan 24 jam), maksimal sesuai batas kendaraan (bawaan 7 hari).
     * Waktu selesai = waktu mulai + hari × 24 jam.
     *
     * @param  string  $vehicleKey  kunci error untuk batas hari maksimal milik kendaraan, mis. "items.0.vehicle_id"
     * @return array{start_at: Carbon, end_at: Carbon, days: int, unit_price: int, subtotal: int}
     *
     * @throws ValidationException
     */
    public function calculate(Vehicle $vehicle, string|CarbonInterface $startAt, int $days, string $vehicleKey = 'days'): array
    {
        $start = self::parse($startAt);
        $minimumDays = max(1, (int) ceil(config('rental.min_rental_hours') / 24));
        $maximumDays = $vehicle->effectiveMaxRentalDays();

        $this->ensureStartNotInPast($start);

        if ($days < $minimumDays) {
            $this->reject('days', "Durasi sewa minimal {$minimumDays} hari.");
        }

        if ($days > $maximumDays) {
            $this->reject($vehicleKey, "Durasi sewa maksimal {$maximumDays} hari untuk {$vehicle->brand} {$vehicle->model}.");
        }

        return [
            'start_at' => $start,
            'end_at' => $start->copy()->addDays($days),
            'days' => $days,
            'unit_price' => $vehicle->price_per_day,
            'subtotal' => $days * $vehicle->price_per_day,
        ];
    }

    /**
     * Daftar durasi yang dapat dipilih untuk waktu mulai tertentu, dari 1 hari sampai batas kendaraan. Berhenti pada
     * durasi pertama yang bentrok dengan pesanan atau servis berikutnya, termasuk jeda pengembalian.
     *
     * @return Collection<int, array{days: int, end_at: Carbon, end_label: string, subtotal: int}>
     *
     * @throws ValidationException
     */
    public function options(Vehicle $vehicle, string|CarbonInterface $startAt): Collection
    {
        $start = self::parse($startAt);
        $this->ensureStartNotInPast($start);

        $options = collect();
        $minimumDays = max(1, (int) ceil(config('rental.min_rental_hours') / 24));

        for ($days = $minimumDays; $days <= $vehicle->effectiveMaxRentalDays(); $days++) {
            $quote = $this->calculate($vehicle, $start, $days);

            if (! $this->isAvailable($vehicle, $quote['start_at'], $quote['end_at'])) {
                break;
            }

            $options->push([
                'days' => $days,
                'end_at' => $quote['end_at'],
                'end_label' => $this->label($quote['end_at']),
                'subtotal' => $quote['subtotal'],
            ]);
        }

        return $options;
    }

    /**
     * Memastikan unit tampil di katalog dan kosong pada rentang itu. Pesanan menahan unit sampai selesai + jeda
     * pengembalian, jadi pesanan atau servis lain tidak boleh mulai sebelum itu.
     *
     * @throws ValidationException
     */
    public function ensureAvailable(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end, string $key = 'start_at'): void
    {
        if (! $this->isAvailable($vehicle, $start, $end)) {
            $this->reject($key, 'Kendaraan tidak tersedia pada rentang waktu ini.');
        }
    }

    public function isAvailable(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end): bool
    {
        return Vehicle::query()
            ->availableBetween($start, $end->copy()->addHours(config('rental.return_buffer_hours')))
            ->whereKey($vehicle->getKey())
            ->exists();
    }

    /**
     * Teks tanggal dan jam untuk pengguna, mis. "Jumat, 24/10/2026 12:00 WIB".
     */
    public function label(CarbonInterface $time): string
    {
        $day = Weekday::cases()[$time->dayOfWeekIso - 1]->label();

        return "{$day}, {$time->format('d/m/Y H:i')} ".config('rental.timezone_label');
    }

    /**
     * @throws ValidationException
     */
    private function ensureStartNotInPast(CarbonInterface $start): void
    {
        if ($start->lt(now()->startOfMinute())) {
            $this->reject('start_at', 'Waktu mulai tidak boleh di masa lalu.');
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
