<?php

namespace App\Http\Resources;

use App\Models\VehicleInspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Inspeksi tanpa jejak penyunting (`edited_by` dan `edited_at` hanya tersimpan di database).
 *
 * @mixin VehicleInspection
 */
class VehicleInspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'order_item_id' => $this->order_item_id,
            'vehicle_id' => $this->vehicle_id,
            'odometer_km' => $this->odometer_km,
            'energy_level' => $this->energy_level,
            'equipment_checks' => $this->equipment_checks,
            'note' => $this->note,
            'inspected_at' => $this->inspected_at,
        ];
    }
}
