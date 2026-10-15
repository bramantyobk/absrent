<?php

namespace App\Actions\Orders;

use App\InspectionType;
use App\Models\OrderVehicleChange;
use App\Models\User;
use App\Models\Vehicle;
use App\OldVehicleStatus;
use App\Services\InspectionRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveOldVehicle
{
    public function __construct(private InspectionRecorder $inspections) {}

    /**
     * Menyelesaikan unit lama yang Ditunda: mengisi inspeksi pengembalian (unit langsung bebas, tanpa jeda) atau menandainya
     * Hilang/dicuri dengan catatan (unit disembunyikan dari katalog). Hanya untuk unit yang berstatus Ditunda.
     *
     * @param  array<string, mixed>  $inspection  isian inspeksi pengembalian (untuk `Returned`)
     *
     * @throws ValidationException
     */
    public function handle(OrderVehicleChange $change, User $actor, OldVehicleStatus $to, array $inspection = [], ?string $note = null): OrderVehicleChange
    {
        return DB::transaction(function () use ($change, $actor, $to, $inspection, $note): OrderVehicleChange {
            $locked = OrderVehicleChange::query()->whereKey($change->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->old_vehicle_status !== OldVehicleStatus::Pending || $to === OldVehicleStatus::Pending) {
                throw ValidationException::withMessages(['action' => 'Unit lama ini tidak sedang berstatus Ditunda.']);
            }

            $vehicle = Vehicle::query()->whereKey($locked->from_vehicle_id)->lockForUpdate()->firstOrFail();
            $attributes = ['old_vehicle_status' => $to, 'old_vehicle_status_by' => $actor->getKey(), 'old_vehicle_status_at' => now()];

            if ($to === OldVehicleStatus::Returned) {
                $attributes['old_vehicle_inspection_id'] = $this->inspections
                    ->record($locked->orderItem, $vehicle, InspectionType::Return, $inspection, $actor, 'inspection')
                    ->getKey();
            } else {
                $attributes['old_vehicle_note'] = $note;
                $vehicle->update(['is_available' => false]);
            }

            $locked->update($attributes);

            return $locked->load(['fromVehicle', 'toVehicle', 'orderItem.order']);
        });
    }
}
