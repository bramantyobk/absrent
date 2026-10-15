<?php

namespace App\Http\Resources;

use App\Models\OrderVehicleChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderVehicleChange
 */
class OrderVehicleChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'from_vehicle' => $this->whenLoaded('fromVehicle', fn () => ['id' => $this->fromVehicle->id, 'name' => "{$this->fromVehicle->brand} {$this->fromVehicle->model}", 'plate_number' => $this->fromVehicle->plate_number]),
            'to_vehicle' => $this->whenLoaded('toVehicle', fn () => ['id' => $this->toVehicle->id, 'name' => "{$this->toVehicle->brand} {$this->toVehicle->model}", 'plate_number' => $this->toVehicle->plate_number]),
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'from_order_status' => $this->from_order_status->value,
            'note' => $this->note,
            'old_unit_price' => $this->old_unit_price,
            'new_unit_price' => $this->new_unit_price,
            'suggested_amount' => $this->suggested_amount,
            'final_amount' => $this->final_amount,
            'old_vehicle_status' => $this->old_vehicle_status?->value,
            'old_vehicle_status_label' => $this->old_vehicle_status?->label(),
            'old_vehicle_note' => $this->old_vehicle_note,
            'old_vehicle_status_at' => $this->old_vehicle_status_at,
            'order_total_price' => $this->whenLoaded('orderItem', fn () => $this->orderItem->relationLoaded('order') ? $this->orderItem->order->total_price : null),
            'created_at' => $this->created_at,
        ];
    }
}
