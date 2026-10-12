<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Vehicles\UpdateVehicleOdometer;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVehicleOdometerRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;

class VehicleOdometerController extends Controller
{
    /**
     * Perbarui odometer
     *
     * Dicatat sebagai riwayat (inspeksi manual). Nilai lebih kecil dari odometer terakhir ditolak, kecuali koreksi oleh admin dengan catatan.
     */
    public function store(UpdateVehicleOdometerRequest $request, Vehicle $vehicle, UpdateVehicleOdometer $updateOdometer): VehicleResource
    {
        $updateOdometer->handle($vehicle, $request->user(), $request->integer('odometer_km'), $request->input('note'));

        return new VehicleResource($vehicle->refresh());
    }
}
