<?php

namespace App\Actions\Vehicles;

use App\InspectionType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use Illuminate\Support\Facades\DB;

class UpdateVehicleOdometer
{
    /**
     * Mencatat odometer baru sebagai inspeksi bertipe manual (riwayat, tidak menimpa data lama)
     * dan memperbarui odometer terakhir kendaraan.
     */
    public function handle(Vehicle $vehicle, User $actor, int $odometerKm, ?string $note = null): VehicleInspection
    {
        return DB::transaction(function () use ($vehicle, $actor, $odometerKm, $note): VehicleInspection {
            Vehicle::query()->whereKey($vehicle->getKey())->lockForUpdate()->first();

            $inspection = $vehicle->inspections()->create([
                'type' => InspectionType::Manual,
                'odometer_km' => $odometerKm,
                'note' => $note,
                'inspected_by' => $actor->getKey(),
                'inspected_at' => now(),
            ]);

            $vehicle->update(['odometer_km' => $odometerKm]);

            return $inspection;
        });
    }
}
