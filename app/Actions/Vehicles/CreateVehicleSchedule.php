<?php

namespace App\Actions\Vehicles;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateVehicleSchedule
{
    public function __construct(private EnsureScheduleIsFree $ensureScheduleIsFree) {}

    /**
     * Baris kendaraan dikunci selama pengecekan bentrok agar tidak ada pesanan atau servis yang menyelip.
     *
     * @param  array{type: string, start_at: string, end_at: string, note?: ?string, odometer_km?: ?int}  $data
     */
    public function handle(Vehicle $vehicle, array $data, User $actor): VehicleSchedule
    {
        return DB::transaction(function () use ($vehicle, $data, $actor): VehicleSchedule {
            Vehicle::query()->whereKey($vehicle->getKey())->lockForUpdate()->first();

            $this->ensureScheduleIsFree->handle($vehicle, Carbon::parse($data['start_at']), Carbon::parse($data['end_at']));

            return $vehicle->schedules()->create($data + ['created_by' => $actor->getKey()]);
        });
    }
}
