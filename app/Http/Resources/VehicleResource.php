<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->displayStatus();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'model' => $this->model,
            'plate_number' => $this->plate_number,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'year' => $this->year,
            'color' => $this->color,
            'price_per_day' => $this->price_per_day,
            'price_per_hour' => $this->price_per_hour,
            'photo_url' => $this->photo_url,
            'is_available' => $this->is_available,
            'status' => $status->value,
            'status_label' => $status->label(),
            'drive_type' => $this->drive_type?->value,
            'drive_type_label' => $this->drive_type?->label(),
            'energy_capacity' => $this->energy_capacity === null ? null : (float) $this->energy_capacity,
            'energy_unit' => $this->drive_type?->energyUnit(),
            'description' => $this->description,
            'odometer_km' => $this->odometer_km,
            'max_rental_days' => $this->max_rental_days,
            'effective_max_rental_days' => $this->effectiveMaxRentalDays(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
