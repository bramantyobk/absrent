<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pratinjau syarat dan ketentuan untuk calon pesanan (belum dibuat): periode dan kendaraan seperti checkout.
 */
class TermsPreviewRequest extends FormRequest
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
            'vehicle_ids' => ['required', 'array', 'min:1', 'max:'.config('rental.max_vehicles_per_order')],
            'vehicle_ids.*' => ['required', 'integer', 'distinct', 'exists:vehicles,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function vehicleIds(): array
    {
        return array_map('intval', $this->validated('vehicle_ids'));
    }
}
