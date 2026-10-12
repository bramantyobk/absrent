<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreVehicleRequest extends VehicleRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Vehicle::class);
    }

    protected function uniquePlateRule(): Unique
    {
        return Rule::unique(Vehicle::class);
    }

    /**
     * Odometer awal hanya diisi saat tambah. Perubahan berikutnya lewat Perbarui odometer.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + ['odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999']];
    }
}
