<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data kendaraan untuk pelanggan: tanpa plat nomor dan odometer.
 *
 * @mixin Vehicle
 */
class CatalogVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'model' => $this->model,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'drive_type' => $this->drive_type?->value,
            'drive_type_label' => $this->drive_type?->label(),
            'year' => $this->year,
            'color' => $this->color,
            'price_per_day' => $this->price_per_day,
            'price_per_hour' => $this->price_per_hour,
            'energy_capacity' => $this->energy_capacity === null ? null : (float) $this->energy_capacity,
            'energy_unit' => $this->drive_type?->energyUnit(),
            'description' => $this->description,
            'photo_url' => $this->photo_url,
            'max_rental_days' => $this->effectiveMaxRentalDays(),
            'min_rental_hours' => config('rental.min_rental_hours'),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment->map(fn ($item): array => [
                'name' => $item->name,
                'quantity' => $item->quantity,
            ])),
        ];
    }
}
