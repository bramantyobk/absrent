<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;

class UpdateVehicleRentalLimit
{
    /**
     * Null mengembalikan unit ke batas bawaan. Pesanan yang sudah dibuat tidak terpengaruh.
     */
    public function handle(Vehicle $vehicle, ?int $maxRentalDays): Vehicle
    {
        $vehicle->update(['max_rental_days' => $maxRentalDays]);

        return $vehicle;
    }
}
