<?php

namespace App\Services;

use App\Models\Vehicle;
use App\OrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class VehicleFreeWindows
{
    public const HORIZON_DAYS = 60;

    /**
     * Rentang waktu bebas di antara pesanan aktif dan jadwal servis, sudah termasuk jeda pengembalian,
     * dimulai dari $from sampai batas horizon. Celah yang lebih pendek dari durasi minimum tidak ditampilkan.
     * Hanya alat bantu memilih waktu servis, penolakan bentrok tetap dijaga saat jadwal disimpan.
     *
     * @return Collection<int, array{start_at: CarbonInterface, end_at: CarbonInterface}>
     */
    public function for(Vehicle $vehicle, ?CarbonInterface $from = null): Collection
    {
        $from ??= now();
        $horizon = $from->copy()->addDays(self::HORIZON_DAYS);
        $buffer = config('rental.return_buffer_hours');
        $minimumHours = config('rental.min_free_window_hours');

        $busy = $vehicle->orderItems()
            ->holdingBetween($from, $horizon)
            ->with('order')
            ->get()
            ->toBase()
            ->map(function ($item) use ($buffer, $from, $horizon): array {
                $releasedAt = $item->end_at->copy()->addHours($buffer);
                $isLate = $item->order->status === OrderStatus::Rented && $releasedAt->lte($from);

                return [$item->start_at, $isLate ? $horizon : $releasedAt];
            })
            ->merge(
                $vehicle->conflictingUnreturnedChanges($horizon)
                    ->toBase()
                    ->map(fn ($change): array => [$change->orderItem->start_at, $horizon])
            )
            ->merge(
                $vehicle->schedules()
                    ->overlapping($from, $horizon)
                    ->get()
                    ->toBase()
                    ->map(fn ($schedule): array => [$schedule->start_at, $schedule->end_at])
            )
            ->sortBy(fn (array $interval) => $interval[0]->getTimestamp())
            ->values();

        $windows = collect();
        $cursor = $from->copy();

        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($busyStart->gt($cursor)) {
                $windows->push(['start_at' => $cursor->copy(), 'end_at' => $busyStart->copy()]);
            }

            if ($busyEnd->gt($cursor)) {
                $cursor = $busyEnd->copy();
            }
        }

        if ($cursor->lt($horizon)) {
            $windows->push(['start_at' => $cursor->copy(), 'end_at' => $horizon->copy()]);
        }

        return $windows
            ->filter(fn (array $window): bool => $window['start_at']->diffInMinutes($window['end_at']) >= $minimumHours * 60)
            ->values();
    }
}
