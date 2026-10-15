<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_price' => $this->total_price,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'vehicle_id' => $item->vehicle_id,
                'vehicle_name' => "{$item->vehicle->brand} {$item->vehicle->model}",
                'photo_url' => $item->vehicle->photo_url,
                'start_at' => $item->start_at,
                'end_at' => $item->end_at,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ])),
            'transaction' => $this->whenLoaded('transaction', fn () => $this->transaction ? [
                'status' => $this->transaction->status->value,
                'status_label' => $this->transaction->status->label(),
                'amount' => $this->transaction->amount,
            ] : null),
        ];
    }
}
