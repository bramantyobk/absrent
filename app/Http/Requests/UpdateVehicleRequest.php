<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateVehicleRequest extends VehicleRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('vehicle'));
    }

    protected function uniquePlateRule(): Unique
    {
        return Rule::unique(Vehicle::class)->ignore($this->route('vehicle'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + ['remove_photo' => ['boolean']];
    }
}
