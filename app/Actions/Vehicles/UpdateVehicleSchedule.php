<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateVehicleSchedule
{
    public function __construct(private EnsureScheduleIsFree $ensureScheduleIsFree) {}

    /**
     * Jadwal yang belum mulai boleh diubah penuh. Jadwal yang sedang berjalan hanya boleh dipersingkat
     * atau diperpanjang (waktu selesai, catatan, odometer), dan perpanjangan melewati pengecekan bentrok
     * yang sama. Jadwal yang sudah lewat dipertahankan sebagai riwayat.
     *
     * @param  array{type: string, start_at: string, end_at: string, note?: ?string, odometer_km?: ?int}  $data
     *
     * @throws ValidationException
     */
    public function handle(VehicleSchedule $schedule, array $data): VehicleSchedule
    {
        return DB::transaction(function () use ($schedule, $data): VehicleSchedule {
            Vehicle::query()->whereKey($schedule->vehicle_id)->lockForUpdate()->first();

            $schedule->refresh();

            if ($schedule->hasEnded()) {
                $this->reject('start_at', 'Jadwal yang sudah lewat tidak dapat diubah agar riwayat servis tetap ada.');
            }

            $start = Carbon::parse($data['start_at']);
            $end = Carbon::parse($data['end_at']);

            if ($schedule->hasStarted()) {
                if ($start->format('Y-m-d H:i') !== $schedule->start_at->format('Y-m-d H:i')) {
                    $this->reject('start_at', 'Servis yang sedang berjalan hanya dapat dipersingkat atau diperpanjang. Waktu mulai tidak dapat diubah.');
                }

                if ($end->lte(now())) {
                    $this->reject('end_at', 'Waktu selesai harus setelah waktu sekarang. Untuk mengakhiri servis, pilih waktu selesai yang akan datang.');
                }

                $start = $schedule->start_at;
                $data['start_at'] = $schedule->start_at;
                $data['type'] = $schedule->type->value;
            } elseif ($start->lt(now()->startOfMinute())) {
                $this->reject('start_at', 'Waktu mulai tidak boleh di masa lalu.');
            }

            $this->ensureScheduleIsFree->handle($schedule->vehicle, $start, $end, $schedule->getKey());

            $schedule->update($data);

            return $schedule;
        });
    }

    /**
     * @throws ValidationException
     */
    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message])->errorBag(EnsureScheduleIsFree::ERROR_BAG);
    }
}
