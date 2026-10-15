<?php

namespace App\Http\Resources;

use App\Models\CancellationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CancellationRequest
 */
class CancellationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_code' => $this->whenLoaded('order', fn () => $this->order->code),
            'customer' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'phone' => $this->user->phone]),
            'reason' => $this->reason,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'handled_by' => $this->whenLoaded('handler', fn () => $this->handler?->name),
            'handled_note' => $this->handled_note,
            'handled_at' => $this->handled_at,
            'created_at' => $this->created_at,
        ];
    }
}
