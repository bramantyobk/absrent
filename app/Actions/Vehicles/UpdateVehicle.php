<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use App\Services\VehiclePhotoStorage;
use Illuminate\Http\UploadedFile;
use Throwable;

class UpdateVehicle
{
    public function __construct(private VehiclePhotoStorage $photos) {}

    /**
     * Foto baru menggantikan foto lama, dan foto lama juga dilepas bila $removePhoto true.
     * Foto lama baru dihapus setelah pembaruan berhasil.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Vehicle $vehicle, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Vehicle
    {
        $oldPhoto = $vehicle->photo;
        $newPhoto = $photo ? $this->photos->store($photo) : null;

        if ($newPhoto) {
            $data['photo'] = $newPhoto;
        } elseif ($removePhoto) {
            $data['photo'] = null;
        }

        try {
            $vehicle->update($data);
        } catch (Throwable $exception) {
            $this->photos->delete($newPhoto);

            throw $exception;
        }

        if ($vehicle->photo !== $oldPhoto) {
            $this->photos->delete($oldPhoto);
        }

        return $vehicle;
    }
}
