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
            'status_note' => $this->status_note,
            'is_manual' => $this->isManual(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'total_price' => $this->total_price,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'customer' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'phone' => $this->user->phone]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'order_item_id' => $item->id,
                'vehicle_id' => $item->vehicle_id,
                'vehicle_name' => "{$item->vehicle->brand} {$item->vehicle->model}",
                'photo_url' => $item->vehicle->photo_url,
                'start_at' => $item->start_at,
                'end_at' => $item->end_at,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'inspections' => $item->relationLoaded('inspections') ? $item->inspections->sortBy('id')->values()->map(fn ($inspection): array => [
                    'type' => $inspection->type->value,
                    'odometer_km' => $inspection->odometer_km,
                    'energy_level' => $inspection->energy_level,
                    'equipment_checks' => $inspection->equipment_checks,
                    'note' => $inspection->note,
                    'inspected_at' => $inspection->inspected_at,
                ]) : null,
            ])),
            'transaction' => $this->whenLoaded('transaction', fn () => $this->transaction ? [
                'status' => $this->transaction->status->value,
                'status_label' => $this->transaction->status->label(),
                'amount' => $this->transaction->amount,
                'confirmed_at' => $this->transaction->confirmed_at,
                'has_proof' => filled($this->transaction->proof_path),
                'note' => $this->transaction->note,
                'bank_account' => $this->transaction->relationLoaded('bankAccount') && $this->transaction->bankAccount ? [
                    'id' => $this->transaction->bankAccount->id,
                    'bank_name' => $this->transaction->bankAccount->bank_name,
                    'account_number' => $this->transaction->bankAccount->account_number,
                    'account_holder' => $this->transaction->bankAccount->account_holder,
                ] : null,
            ] : null),
            'vehicle_changes' => $this->whenLoaded('items', fn () => $this->items->every(fn ($item) => $item->relationLoaded('vehicleChanges')) ? OrderVehicleChangeResource::collection($this->items->flatMap->vehicleChanges->sortBy('id')->values()) : null),
            'cancellation_request' => $this->whenLoaded('cancellationRequests', fn () => ($request = $this->cancellationRequests->sortByDesc('id')->first()) ? new CancellationRequestResource($request) : null),
        ];
    }
}
