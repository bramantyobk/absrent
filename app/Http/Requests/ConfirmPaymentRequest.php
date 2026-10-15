<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Konfirmasi pembayaran: pelanggan memilih rekening perusahaan yang dipakai transfer. Dipakai bersama Blade dan API.
 */
class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        Gate::authorize('pay', $order->loadMissing('transaction'));

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'Pilih rekening bank yang Anda gunakan untuk transfer.',
            'bank_account_id.exists' => 'Rekening tujuan tidak tersedia. Pilih rekening lain.',
        ];
    }
}
