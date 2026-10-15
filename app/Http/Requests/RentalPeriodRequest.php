<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Periode sewa satu kendaraan: waktu mulai (WIB bila tanpa offset) dan jumlah hari. Aturan durasi dan ketersediaan
 * dijaga RentalQuote.
 */
class RentalPeriodRequest extends FormRequest
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
        ];
    }
}
