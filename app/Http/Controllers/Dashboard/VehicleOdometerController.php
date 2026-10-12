<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Vehicles\UpdateVehicleOdometer;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVehicleOdometerRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;

class VehicleOdometerController extends Controller
{
    public function store(UpdateVehicleOdometerRequest $request, Vehicle $vehicle, UpdateVehicleOdometer $updateOdometer): RedirectResponse
    {
        $updateOdometer->handle($vehicle, $request->user(), $request->integer('odometer_km'), $request->input('note'));

        return redirect()
            ->route('dashboard.vehicles.show', $vehicle)
            ->with('status', 'Odometer berhasil diperbarui.');
    }
}
