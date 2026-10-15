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
            'start_at' => ['nullable', 'date', 'required_with:days'],
            'days' => ['nullable', 'integer', 'min:1', 'max:30', 'required_with:start_at'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_at.required_with' => 'Isi waktu mulai dan durasi bersamaan.',
            'days.required_with' => 'Isi waktu mulai dan durasi bersamaan.',
        ];
    }
}
