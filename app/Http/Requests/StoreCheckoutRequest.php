<?php

namespace App\Http\Requests;

use App\Services\CheckoutCart;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checkout lewat API: daftar item dikirim dalam body. Header `Idempotency-Key` mencegah pesanan ganda.
 */
class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.CheckoutCart::MAX_ITEMS],
            'items.*.vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'items.*.start_at' => ['required', 'date'],
            'items.*.end_at' => ['required', 'date'],
        ];
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return $key === null ? null : substr($key, 0, 100);
    }
}
