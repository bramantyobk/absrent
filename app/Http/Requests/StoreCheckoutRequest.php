<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checkout lewat API: satu periode (waktu mulai + hari) untuk semua kendaraan. Pelanggan wajib menyetujui syarat dan ketentuan (`terms_accepted`). Header `Idempotency-Key` mencegah pesanan ganda.
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
            'start_at' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
            'terms_accepted' => ['required', 'accepted'],
            'items' => ['required', 'array', 'min:1', 'max:'.config('rental.max_vehicles_per_order')],
            'items.*.vehicle_id' => ['required', 'integer', 'distinct', 'exists:vehicles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_accepted.required' => 'Anda harus menyetujui syarat dan ketentuan.',
            'terms_accepted.accepted' => 'Anda harus menyetujui syarat dan ketentuan.',
        ];
    }

    /**
     * @return list<int>
     */
    public function vehicleIds(): array
    {
        return array_map('intval', array_column($this->validated('items'), 'vehicle_id'));
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return $key === null ? null : substr($key, 0, 100);
    }
}
