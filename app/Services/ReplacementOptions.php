<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Vehicle;
use App\OrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Unit pengganti yang dapat dipilih untuk satu item pesanan Booking atau Disewa.
 */
class ReplacementOptions
{
    /**
     * Rentang yang diperiksa: untuk pesanan Disewa dihitung dari sekarang sampai `end_at`, untuk Booking seluruh rentang pesanan.
     * Jeda pengembalian ditambahkan di akhir rentang, dan item itu sendiri dikecualikan.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function window(OrderItem $item): array
    {
        $isRented = $item->order->status === OrderStatus::Rented;
        $start = $isRented && $item->start_at->lt(now()) ? now() : $item->start_at;

        return [$start, $item->end_at->copy()->addHours(config('rental.return_buffer_hours'))];
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function for(OrderItem $item): Collection
    {
        $item->loadMissing('order');
        [$start, $end] = $this->window($item);

        return Vehicle::query()
            ->availableBetween($start, $end, $item->getKey())
            ->whereKeyNot($item->vehicle_id)
            ->orderBy('brand')
            ->orderBy('model')
            ->orderBy('id')
            ->get();
    }

    /**
     * Selisih saran: jumlah hari dikali selisih tarif harian (tarif unit baru dikurangi tarif yang berlaku pada item).
     */
    public function suggestedAmount(OrderItem $item, Vehicle $replacement): int
    {
        return $item->days * ($replacement->price_per_day - $item->unit_price);
    }
}
