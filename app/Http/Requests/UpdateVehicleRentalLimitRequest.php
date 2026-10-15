<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateVehicleRentalLimitRequest extends FormRequest
{
    public const ERROR_BAG = 'rental_limit';

    public const MAX_DAYS = 30;

    protected $errorBag = self::ERROR_BAG;

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('vehicle') ?? Vehicle::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('max_rental_days') === '') {
            $this->merge(['max_rental_days' => null]);
        }
    }

    /**
     * Kosong berarti memakai batas bawaan (config rental.max_rental_days).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'max_rental_days' => ['present', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_DAYS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_rental_days.max' => 'Hari sewa maksimal tidak boleh lebih dari '.self::MAX_DAYS.' hari.',
            'max_rental_days.min' => 'Hari sewa maksimal minimal 1 hari.',
        ];
    }
}
