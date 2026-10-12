<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Vehicles\CancelVehicleSchedule;
use App\Actions\Vehicles\CreateVehicleSchedule;
use App\Actions\Vehicles\UpdateVehicleSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleScheduleRequest;
use App\Http\Requests\UpdateVehicleScheduleRequest;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class VehicleScheduleController extends Controller
{
    public function store(StoreVehicleScheduleRequest $request, Vehicle $vehicle, CreateVehicleSchedule $createSchedule): RedirectResponse
    {
        $createSchedule->handle($vehicle, $request->validated(), $request->user());

        return redirect()
            ->route('dashboard.vehicles.show', $vehicle)
            ->with('status', 'Jadwal servis berhasil ditambahkan.');
    }

    public function update(UpdateVehicleScheduleRequest $request, Vehicle $vehicle, VehicleSchedule $schedule, UpdateVehicleSchedule $updateSchedule): RedirectResponse
    {
        $updateSchedule->handle($schedule, $request->validated());

        return redirect()
            ->route('dashboard.vehicles.show', $vehicle)
            ->with('status', 'Jadwal servis berhasil diperbarui.');
    }

    public function destroy(Vehicle $vehicle, VehicleSchedule $schedule, CancelVehicleSchedule $cancelSchedule): RedirectResponse
    {
        Gate::authorize('delete', $schedule);

        $cancelSchedule->handle($schedule);

        return redirect()
            ->route('dashboard.vehicles.show', $vehicle)
            ->with('status', 'Jadwal servis berhasil dibatalkan.');
    }
}
