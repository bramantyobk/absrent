<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use App\Services\VehiclePhotoStorage;
use Illuminate\Database\QueryException;

class DeleteVehicle
{
    public function __construct(private VehiclePhotoStorage $photos) {}

    /**
     * Kendaraan yang sudah pernah dipesan dijaga foreign key (restrictOnDelete), jadi pengecekan memakai
     * pelanggaran constraint itu sendiri agar tetap aman saat ada pesanan masuk bersamaan.
     *
     * @return bool false bila kendaraan tidak dapat dihapus karena memiliki riwayat pesanan
     */
    public function handle(Vehicle $vehicle): bool
    {
        try {
            $vehicle->delete();
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return false;
        }

        $this->photos->delete($vehicle->photo);

        return true;
    }
}
