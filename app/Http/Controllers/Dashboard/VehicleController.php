<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Vehicles\CreateVehicle;
use App\Actions\Vehicles\DeleteVehicle;
use App\Actions\Vehicles\UpdateVehicle;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Requests\VehicleFilterRequest;
use App\Models\Vehicle;
use App\VehicleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(VehicleFilterRequest $request): View
    {
        $filters = $request->validated();

        $vehicles = Vehicle::query()
            ->filter($filters)
            ->orderBy('brand')
            ->orderBy('model')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return view('dashboard.vehicles.index', [
            'vehicles' => $vehicles,
            'types' => VehicleType::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Vehicle::class);

        return view('dashboard.vehicles.create', ['types' => VehicleType::cases()]);
    }

    public function store(StoreVehicleRequest $request, CreateVehicle $createVehicle): RedirectResponse
    {
        $createVehicle->handle($request->vehicleAttributes(), $request->file('photo'));

        return redirect()
            ->route('dashboard.vehicles.index')
            ->with('status', 'Kendaraan berhasil ditambahkan.');
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        return view('dashboard.vehicles.edit', [
            'vehicle' => $vehicle,
            'types' => VehicleType::cases(),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, UpdateVehicle $updateVehicle): RedirectResponse
    {
        $updateVehicle->handle(
            $vehicle,
            $request->vehicleAttributes(),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        );

        return redirect()
            ->route('dashboard.vehicles.index')
            ->with('status', 'Kendaraan berhasil diperbarui.');
    }

    public function destroy(Vehicle $vehicle, DeleteVehicle $deleteVehicle): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        if (! $deleteVehicle->handle($vehicle)) {
            return back()->with('error', 'Kendaraan memiliki riwayat pesanan. Tandai sebagai tidak tersedia sebagai gantinya.');
        }

        return redirect()
            ->route('dashboard.vehicles.index')
            ->with('status', 'Kendaraan berhasil dihapus.');
    }
}
