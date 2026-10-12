<?php

namespace App\Actions\Vehicles;

use App\Models\VehicleSchedule;
use Illuminate\Validation\ValidationException;

class CancelVehicleSchedule
{
    /**
     * Hanya jadwal yang belum mulai yang dapat dibatalkan. Jadwal yang sedang berjalan dipersingkat,
     * dan jadwal yang sudah lewat tidak dihapus agar riwayat servis tetap ada.
     *
     * @throws ValidationException
     */
    public function handle(VehicleSchedule $schedule): void
    {
        if ($schedule->hasStarted()) {
            throw ValidationException::withMessages([
                'schedule' => $schedule->hasEnded()
                    ? 'Jadwal yang sudah lewat tidak dihapus agar riwayat servis tetap ada.'
                    : 'Servis yang sedang berjalan tidak dapat dibatalkan. Persingkat waktu selesainya.',
            ])->errorBag(EnsureScheduleIsFree::ERROR_BAG);
        }

        $schedule->delete();
    }
}
