<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\VehicleFreeWindows;
use App\Services\VehicleTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class VehicleAvailabilityController extends Controller
{
    /**
     * Jendela kosong
     *
     * Rentang waktu bebas 60 hari ke depan di antara pesanan aktif dan jadwal servis, sudah termasuk jeda pengembalian.
     */
    public function freeWindows(Vehicle $vehicle, VehicleFreeWindows $freeWindows): JsonResponse
    {
        Gate::authorize('view', $vehicle);

        return response()->json(['data' => $freeWindows->for($vehicle)->values()]);
    }

    /**
     * Riwayat jadwal
     *
     * Pesanan dan jadwal servis dalam satu daftar kronologis (terbaru di atas), dengan odometer awal dan akhir.
     */
    public function timeline(Vehicle $vehicle, VehicleTimeline $timeline): JsonResponse
    {
        Gate::authorize('view', $vehicle);

        return response()->json([
            'data' => $timeline->for($vehicle)
                ->map(fn (array $row): array => array_diff_key($row, ['schedule' => true]) + [
                    'schedule_id' => $row['schedule']?->id,
                ])
                ->values(),
        ]);
    }
}
