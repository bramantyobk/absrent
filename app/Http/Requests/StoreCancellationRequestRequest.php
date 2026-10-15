<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Pengajuan pembatalan pesanan Lunas oleh pelanggan, dengan alasan.
 */
class StoreCancellationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        Gate::authorize('requestCancellation', $order->loadMissing('transaction'));

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan pembatalan terlalu singkat.',
        ];
    }
}
