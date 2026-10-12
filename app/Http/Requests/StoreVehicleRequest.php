<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
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
}
