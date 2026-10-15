<?php

namespace App\Http\Requests;

use App\VehicleDriveType;
use App\VehicleType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogFilterRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(VehicleType::class)],
            'drive_type' => ['nullable', Rule::enum(VehicleDriveType::class)],
            'start_at' => ['nullable', 'date', 'required_with:end_at'],
            'end_at' => ['nullable', 'date', 'required_with:start_at', 'after:start_at'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_at.required_with' => 'Isi waktu mulai dan selesai bersamaan.',
            'end_at.required_with' => 'Isi waktu mulai dan selesai bersamaan.',
            'end_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }
}
