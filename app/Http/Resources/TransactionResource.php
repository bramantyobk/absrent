<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transaksi untuk staf. Bukti pembayaran dilayani endpoint terotorisasi (`proof_url`), bukan URL publik.
 *
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
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
            'order_status' => $this->whenLoaded('order', fn () => $this->order->status->value),
            'customer' => $this->whenLoaded('order', fn () => $this->order->relationLoaded('user') ? ['id' => $this->order->user->id, 'name' => $this->order->user->name, 'phone' => $this->order->user->phone] : null),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount' => $this->amount,
            'confirmed_at' => $this->confirmed_at,
            'verification_expires_at' => $this->whenLoaded('order', fn () => $this->status->value === 'verifying' ? $this->order->expires_at : null),
            'is_near_expiry' => $this->whenLoaded('order', fn () => $this->isNearExpiry()),
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount ? [
                'id' => $this->bankAccount->id,
                'bank_name' => $this->bankAccount->bank_name,
                'account_number' => $this->bankAccount->account_number,
                'account_holder' => $this->bankAccount->account_holder,
            ] : null),
            'note' => $this->note,
            'proof_url' => $this->proof_path ? route('api.v1.dashboard.transactions.proof', $this->resource) : null,
            'verified_by' => $this->whenLoaded('verifier', fn () => $this->verifier?->name),
            'verified_at' => $this->verified_at,
        ];
    }
}
