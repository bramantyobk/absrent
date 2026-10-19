<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevenueChartRequest;
use App\Services\RevenueReport;
use Illuminate\Http\JsonResponse;

class RevenueController extends Controller
{
    /**
     * Laporan pendapatan
     *
     * Filter `start_date` dan `end_date` (bawaan 30 hari, maksimal 366 hari) serta `type` (kategori kendaraan). Pendapatan dicatat pada tanggal
     * verifikasi transaksi Lunas (subtotal item), dan selisih biaya ganti kendaraan pada tanggal perubahan (bertanda). Status pesanan yang dihitung:
     * `booking`, `rented`, `completed`, dan `stolen`. Nilai dikreditkan ke unit yang sedang terpasang pada item. Respons memuat `summary`,
     * `days` (setiap hari pada rentang), `vehicles` (terbesar dahulu), dan `vehicle_changes` (rincian selisih).
     */
    public function index(RevenueChartRequest $request, RevenueReport $revenueReport): JsonResponse
    {
        $start = $request->startDate();
        $end = $request->endDate();
        $type = $request->vehicleType();

        $days = $revenueReport->daily($start, $end, $type);

        return response()->json(['data' => [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'type' => $type?->value,
            'summary' => ['base' => (int) $days->sum('base'), 'vehicle_change_delta' => (int) $days->sum('delta'), 'total' => (int) $days->sum('total')],
            'days' => $days->map(fn (array $day): array => ['date' => $day['day'], 'base' => $day['base'], 'vehicle_change_delta' => $day['delta'], 'total' => $day['total']])->values(),
            'vehicles' => $revenueReport->byVehicle($start, $end, $type),
            'vehicle_changes' => $revenueReport->changes($start, $end, $type)->map(fn ($change): array => [
                'id' => $change->id,
                'date' => $change->created_at->toDateString(),
                'order_code' => $change->orderItem->order->code,
                'from_vehicle' => "{$change->fromVehicle->brand} {$change->fromVehicle->model}",
                'to_vehicle' => "{$change->toVehicle->brand} {$change->toVehicle->model}",
                'amount' => $change->final_amount,
            ])->values(),
        ]]);
    }
}
