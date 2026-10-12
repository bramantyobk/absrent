<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreVehicleScheduleRequest extends VehicleScheduleRequest
{
    protected function ability(): string
    {
        return 'create';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['start_at'][] = 'after_or_equal:'.now()->startOfMinute()->toDateTimeString();

        return $rules;
    }
}
