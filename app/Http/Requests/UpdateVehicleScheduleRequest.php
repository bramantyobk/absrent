<?php

namespace App\Http\Requests;

class UpdateVehicleScheduleRequest extends VehicleScheduleRequest
{
    protected function ability(): string
    {
        return 'update';
    }
}
