<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CheckoutCart
{
    public const SESSION_KEY = 'checkout.items';

    public const MAX_ITEMS = 10;

    public function __construct(private RentalQuote $rentalQuote) {}

    /**
     * @return list<array{vehicle_id: int, start_at: string, end_at: string}>
     */
    public function items(): array
    {
        return array_values(session(self::SESSION_KEY, []));
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    /**
     * Menambah satu kendaraan beserta jadwalnya ke keranjang setelah aturan durasi dan ketersediaan lolos.
     * Ketersediaan diperiksa lagi (dengan penguncian) saat pesanan dibuat.
     *
     * @throws ValidationException
     */
    public function add(Vehicle $vehicle, string $startAt, string $endAt): void
    {
        if (count($this->items()) >= self::MAX_ITEMS) {
            throw ValidationException::withMessages(['start_at' => 'Satu pesanan maksimal '.self::MAX_ITEMS.' kendaraan.']);
        }

        $quote = $this->rentalQuote->calculate($vehicle, $startAt, $endAt);
        $this->rentalQuote->ensureAvailable($vehicle, $quote['start_at'], $quote['end_at']);

        session([self::SESSION_KEY => [...$this->items(), [
            'vehicle_id' => $vehicle->getKey(),
            'start_at' => $quote['start_at']->toDateTimeString(),
            'end_at' => $quote['end_at']->toDateTimeString(),
        ]]]);
    }

    public function remove(int $index): void
    {
        $items = $this->items();
        unset($items[$index]);

        session([self::SESSION_KEY => array_values($items)]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Isi keranjang untuk ditampilkan: kendaraan, jadwal, hari, subtotal, dan pesan bila item tidak lagi valid.
     *
     * @return Collection<int, array{index: int, vehicle: Vehicle, start_at: string, end_at: string, days: ?int, subtotal: int, error: ?string}>
     */
    public function lines(): Collection
    {
        $items = $this->items();
        $vehicles = Vehicle::query()->whereIn('id', array_column($items, 'vehicle_id'))->get()->keyBy('id');

        return collect($items)->map(function (array $item, int $index) use ($vehicles): ?array {
            $vehicle = $vehicles->get($item['vehicle_id']);

            if (! $vehicle) {
                return null;
            }

            try {
                $quote = $this->rentalQuote->calculate($vehicle, $item['start_at'], $item['end_at']);
                $this->rentalQuote->ensureAvailable($vehicle, $quote['start_at'], $quote['end_at']);
                $error = null;
            } catch (ValidationException $exception) {
                $quote = null;
                $error = collect($exception->errors())->flatten()->first();
            }

            return [
                'index' => $index,
                'vehicle' => $vehicle,
                'start_at' => $item['start_at'],
                'end_at' => $item['end_at'],
                'days' => $quote['days'] ?? null,
                'subtotal' => $quote['subtotal'] ?? 0,
                'error' => $error,
            ];
        })->filter()->values();
    }
}
