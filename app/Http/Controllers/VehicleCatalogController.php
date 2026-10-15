<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\CompanyProfile;
use App\Models\Vehicle;
use App\VehicleDriveType;
use App\VehicleType;
use Illuminate\View\View;

class VehicleCatalogController extends Controller
{
    public function index(CatalogFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('vehicles.index', [
            'vehicles' => Vehicle::query()
                ->listedInCatalog()
                ->catalogFilter($filters)
                ->orderBy('brand')
                ->orderBy('model')
                ->orderBy('id')
                ->paginate($filters['per_page'] ?? 12)
                ->withQueryString(),
            'categories' => VehicleType::cases(),
            'driveTypes' => VehicleDriveType::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(Vehicle $vehicle): View
    {
        abort_unless($vehicle->is_available, 404);

        $vehicle->loadEquipment();

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'supportUrl' => CompanyProfile::current()->supportWhatsAppUrl("Halo, saya ingin bertanya tentang {$vehicle->brand} {$vehicle->model}."),
        ]);
    }
}
