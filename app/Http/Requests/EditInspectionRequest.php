<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Suntingan inspeksi yang sudah tersimpan (khusus admin): odometer, level energi, jumlah perlengkapan yang dicek, dan catatan.
 */
class EditInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('inspection'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'odometer_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'energy_level' => ['required', 'integer', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['required', 'integer', 'between:0,99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'odometer_km.required' => 'Odometer wajib diisi.',
            'odometer_km.integer' => 'Odometer harus berupa angka bulat (km).',
            'energy_level.required' => 'Level energi wajib diisi.',
            'energy_level.between' => 'Level energi harus antara 0 dan 100 persen.',
            'equipment.*.integer' => 'Jumlah perlengkapan harus berupa angka bulat.',
            'equipment.*.between' => 'Jumlah perlengkapan harus antara 0 dan 99.',
        ];
    }
}
