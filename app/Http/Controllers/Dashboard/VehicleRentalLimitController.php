<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Vehicles\UpdateVehicleRentalLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVehicleRentalLimitRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;

class VehicleRentalLimitController extends Controller
{
    public function update(UpdateVehicleRentalLimitRequest $request, Vehicle $vehicle, UpdateVehicleRentalLimit $updateRentalLimit): RedirectResponse
    {
        $updateRentalLimit->handle($vehicle, $request->validated('max_rental_days'));

        return redirect()
            ->route('dashboard.vehicles.show', $vehicle)
            ->with('status', 'Hari sewa maksimal berhasil diperbarui.');
    }
}
