<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Vehicles\UpdateVehicleRentalLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVehicleRentalLimitRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;

class VehicleRentalLimitController extends Controller
{
    /**
     * Atur hari sewa maksimal kendaraan
     *
     * Kirim `max_rental_days` (1 sampai 30), atau `null` untuk memakai batas bawaan (7 hari). Berlaku untuk pesanan berikutnya.
     */
    public function update(UpdateVehicleRentalLimitRequest $request, Vehicle $vehicle, UpdateVehicleRentalLimit $updateRentalLimit): VehicleResource
    {
        return new VehicleResource($updateRentalLimit->handle($vehicle, $request->validated('max_rental_days')));
    }
}
