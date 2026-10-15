<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use App\Services\VehiclePhotoStorage;
use Illuminate\Http\UploadedFile;
use Throwable;

class CreateVehicle
{
    public function __construct(private VehiclePhotoStorage $photos) {}

    /**
     * Foto yang sudah tersimpan dihapus kembali bila penyimpanan kendaraan gagal.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?UploadedFile $photo = null): Vehicle
    {
        $path = $photo ? $this->photos->store($photo) : null;

        try {
            return Vehicle::create($data + ['photo' => $path, 'is_available' => true]);
        } catch (Throwable $exception) {
            $this->photos->delete($path);

            throw $exception;
        }
    }
}
