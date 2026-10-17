<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Pesanan manual (booking langsung) oleh staf: pelanggan yang ada (`customer_id`) atau pelanggan baru (`new_customer`), satu periode
 * dengan maksimal dua kendaraan seperti pesanan online, dan pencatatan pembayaran di tempat (bukti dan rekening opsional).
 */
class StoreManualOrderRequest extends FormRequest
{
    public const MAX_PROOF_KB = 2048;

    public function authorize(): bool
    {
        return Gate::allows('createManual', Order::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('new_customer'))) {
            $this->merge(['new_customer' => [
                'name' => trim((string) $this->input('new_customer.name')),
                'phone' => User::normalizePhone($this->input('new_customer.phone')),
                'email' => trim((string) $this->input('new_customer.email')),
            ]]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isNewCustomer = fn (): bool => blank($this->input('customer_id'));

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'new_customer' => [Rule::requiredIf($isNewCustomer), 'nullable', 'array'],
            'new_customer.name' => [Rule::requiredIf($isNewCustomer), 'nullable', 'string', 'max:255'],
            'new_customer.phone' => [Rule::requiredIf($isNewCustomer), 'nullable', 'string', 'regex:/^62\d{8,13}$/'],
            'new_customer.email' => [Rule::requiredIf($isNewCustomer), 'nullable', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],

            'start_at' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:'.config('rental.max_vehicles_per_order')],
            'items.*.vehicle_id' => ['required', 'integer', 'distinct', 'exists:vehicles,id'],

            'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')],
            'payment_note' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PROOF_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_customer.required' => 'Pilih pelanggan yang ada atau isi data pelanggan baru.',
            'new_customer.name.required' => 'Nama pelanggan wajib diisi.',
            'new_customer.phone.required' => 'Nomor WhatsApp pelanggan wajib diisi.',
            'new_customer.phone.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'new_customer.email.required' => 'Email pelanggan baru wajib diisi.',
            'new_customer.email.unique' => 'Email sudah terdaftar. Cari pelanggan itu dan pilih sebagai pelanggan yang ada.',
            'proof.mimes' => 'Bukti pembayaran harus berformat JPG atau PNG.',
            'proof.max' => 'Bukti pembayaran maksimal 2 MB.',
        ];
    }

    /**
     * Data tervalidasi untuk Action. Pelanggan baru diabaikan bila pelanggan yang ada dipilih.
     *
     * @return array<string, mixed>
     */
    public function orderData(): array
    {
        $data = $this->safe()->except(['proof']);

        if (filled($data['customer_id'] ?? null)) {
            unset($data['new_customer']);
        }

        return $data;
    }
}
