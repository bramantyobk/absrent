<?php

namespace App\Services;

use App\InspectionType;
use App\Models\OrderItem;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class VehicleTimeline
{
    public const LIMIT = 100;

    /**
     * Satu daftar kronologis (terbaru di atas) yang menggabungkan pesanan dan jadwal servis kendaraan.
     * Penggabungan dilakukan di sini, bukan di database.
     *
     * @return Collection<int, array{kind: string, start_at: CarbonInterface, end_at: CarbonInterface, free_at: ?CarbonInterface, title: string, subtitle: ?string, status_label: string, status_class: string, odometer_start: ?int, odometer_end: ?int, distance_km: ?int, note: ?string, schedule: ?VehicleSchedule}>
     */
    public function for(Vehicle $vehicle): Collection
    {
        $buffer = config('rental.return_buffer_hours');

        $orders = $vehicle->orderItems()
            ->with(['order.user', 'inspections'])
            ->latest('start_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (OrderItem $item) use ($buffer): array {
                $odometerStart = $item->inspections->firstWhere('type', InspectionType::Pickup)?->odometer_km;
                $odometerEnd = $item->inspections->firstWhere('type', InspectionType::Return)?->odometer_km;

                return [
                    'kind' => 'order',
                    'start_at' => $item->start_at,
                    'end_at' => $item->end_at,
                    'free_at' => $item->end_at->copy()->addHours($buffer),
                    'title' => $item->order->code,
                    'subtitle' => $item->order->user->name,
                    'status_label' => $item->order->status->label(),
                    'status_class' => $item->order->status->badgeClass(),
                    'odometer_start' => $odometerStart,
                    'odometer_end' => $odometerEnd,
                    'distance_km' => $odometerStart !== null && $odometerEnd !== null ? $odometerEnd - $odometerStart : null,
                    'note' => null,
                    'schedule' => null,
                ];
            });

        $schedules = $vehicle->schedules()
            ->latest('start_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (VehicleSchedule $schedule): array => [
                'kind' => 'schedule',
                'start_at' => $schedule->start_at,
                'end_at' => $schedule->end_at,
                'free_at' => $schedule->end_at,
                'title' => $schedule->type->label(),
                'subtitle' => null,
                'status_label' => $schedule->hasEnded() ? 'Selesai' : ($schedule->hasStarted() ? 'Berjalan' : 'Terjadwal'),
                'status_class' => $schedule->hasEnded() ? 'status-returned' : 'status-progress',
                'odometer_start' => $schedule->odometer_km,
                'odometer_end' => null,
                'distance_km' => null,
                'note' => $schedule->note,
                'schedule' => $schedule,
            ]);

        return $orders->concat($schedules)->sortByDesc(fn (array $row) => $row['start_at']->getTimestamp())->values();
    }
}
