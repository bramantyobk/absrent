<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Services\VehiclePhotoStorage;
use App\VehicleType;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vehicle::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(VehicleType::class)],
            'status' => ['nullable', Rule::in(['available', 'unavailable'])],
        ]);

        $vehicles = Vehicle::query()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(
                fn ($query) => $query
                    ->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")
            ))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_available', $status === 'available'))
            ->orderBy('brand')
            ->orderBy('model')
            ->paginate(10)
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

    public function store(StoreVehicleRequest $request, VehiclePhotoStorage $photos): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'remove_photo']);
        $newPhoto = $request->hasFile('photo') ? $photos->store($request->file('photo')) : null;

        try {
            Vehicle::create($data + ['photo' => $newPhoto]);
        } catch (Throwable $exception) {
            $photos->delete($newPhoto);

            throw $exception;
        }

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

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, VehiclePhotoStorage $photos): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'remove_photo']);
        $oldPhoto = $vehicle->photo;
        $newPhoto = $request->hasFile('photo') ? $photos->store($request->file('photo')) : null;

        if ($newPhoto) {
            $data['photo'] = $newPhoto;
        } elseif ($request->boolean('remove_photo')) {
            $data['photo'] = null;
        }

        try {
            $vehicle->update($data);
        } catch (Throwable $exception) {
            $photos->delete($newPhoto);

            throw $exception;
        }

        if ($vehicle->photo !== $oldPhoto) {
            $photos->delete($oldPhoto);
        }

        return redirect()
            ->route('dashboard.vehicles.index')
            ->with('status', 'Kendaraan berhasil diperbarui.');
    }

    /**
     * Kendaraan yang sudah pernah dipesan dijaga foreign key (restrictOnDelete), jadi pengecekan memakai
     * pelanggaran constraint itu sendiri agar tetap aman saat ada pesanan masuk bersamaan.
     */
    public function destroy(Vehicle $vehicle, VehiclePhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        try {
            $vehicle->delete();
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return back()->with('error', 'Kendaraan memiliki riwayat pesanan. Tandai sebagai tidak tersedia sebagai gantinya.');
        }

        $photos->delete($vehicle->photo);

        return redirect()
            ->route('dashboard.vehicles.index')
            ->with('status', 'Kendaraan berhasil dihapus.');
    }
}
