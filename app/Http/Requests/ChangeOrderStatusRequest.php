<?php

namespace App\Http\Requests;

use App\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Ubah status pesanan oleh staf. `rented` dan `completed` wajib disertai inspeksi tiap unit (`inspections` menurut id item
 * pesanan), sedangkan `stolen` (hanya admin) wajib disertai catatan.
 */
class ChangeOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        if (! Gate::allows('manage', $order)) {
            return false;
        }

        return $this->input('status') !== OrderStatus::Stolen->value || Gate::allows('markStolen', $order);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([OrderStatus::Rented->value, OrderStatus::Completed->value, OrderStatus::Stolen->value])],
            'note' => ['exclude_unless:status,'.OrderStatus::Stolen->value, 'required', 'string', 'max:1000'],
            'inspections' => [Rule::requiredIf(fn (): bool => in_array($this->input('status'), [OrderStatus::Rented->value, OrderStatus::Completed->value], true)), 'array'],
            'inspections.*.odometer_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'inspections.*.energy_level' => ['required', 'integer', 'between:0,100'],
            'inspections.*.note' => ['nullable', 'string', 'max:1000'],
            'inspections.*.equipment' => ['nullable', 'array'],
            'inspections.*.equipment.*' => ['required', 'integer', 'between:0,99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status tidak dikenal.',
            'note.required' => 'Catatan wajib diisi untuk menandai pesanan Dicuri.',
            'inspections.required' => 'Inspeksi harus diisi untuk setiap kendaraan pada pesanan ini.',
            'inspections.*.odometer_km.required' => 'Odometer wajib diisi.',
            'inspections.*.odometer_km.integer' => 'Odometer harus berupa angka bulat (km).',
            'inspections.*.energy_level.required' => 'Level energi wajib diisi.',
            'inspections.*.energy_level.between' => 'Level energi harus antara 0 dan 100 persen.',
            'inspections.*.equipment.*.integer' => 'Jumlah perlengkapan harus berupa angka bulat.',
            'inspections.*.equipment.*.between' => 'Jumlah perlengkapan harus antara 0 dan 99.',
        ];
    }

    /**
     * Inspeksi hasil validasi, menurut id item pesanan (kosong untuk status Dicuri).
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function inspections(): array
    {
        return $this->validated('inspections') ?? [];
    }
}
