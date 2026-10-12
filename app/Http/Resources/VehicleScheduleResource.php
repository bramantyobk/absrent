<?php

namespace App\Http\Resources;

use App\Models\VehicleSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleSchedule
 */
class VehicleScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'note' => $this->note,
            'odometer_km' => $this->odometer_km,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
