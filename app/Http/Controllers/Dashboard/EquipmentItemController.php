<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Equipment\CreateEquipmentItem;
use App\Actions\Equipment\DeleteEquipmentItem;
use App\Actions\Equipment\UpdateEquipmentItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipmentItemFilterRequest;
use App\Http\Requests\StoreEquipmentItemRequest;
use App\Http\Requests\UpdateEquipmentItemRequest;
use App\Models\EquipmentItem;
use App\VehicleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EquipmentItemController extends Controller
{
    public function index(EquipmentItemFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('dashboard.equipment.index', [
            'items' => EquipmentItem::query()
                ->filter($filters)
                ->orderBy('category')
                ->ordered()
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString(),
            'categories' => VehicleType::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', EquipmentItem::class);

        return view('dashboard.equipment.create', ['categories' => VehicleType::cases()]);
    }

    public function store(StoreEquipmentItemRequest $request, CreateEquipmentItem $createEquipmentItem): RedirectResponse
    {
        $createEquipmentItem->handle($request->validated());

        return redirect()
            ->route('dashboard.equipment.index')
            ->with('status', 'Perlengkapan berhasil ditambahkan.');
    }

    public function edit(EquipmentItem $equipment): View
    {
        Gate::authorize('update', $equipment);

        return view('dashboard.equipment.edit', [
            'equipment' => $equipment,
            'categories' => VehicleType::cases(),
        ]);
    }

    public function update(UpdateEquipmentItemRequest $request, EquipmentItem $equipment, UpdateEquipmentItem $updateEquipmentItem): RedirectResponse
    {
        $updateEquipmentItem->handle($equipment, $request->validated());

        return redirect()
            ->route('dashboard.equipment.index')
            ->with('status', 'Perlengkapan berhasil diperbarui.');
    }

    public function destroy(EquipmentItem $equipment, DeleteEquipmentItem $deleteEquipmentItem): RedirectResponse
    {
        Gate::authorize('delete', $equipment);

        $deleteEquipmentItem->handle($equipment);

        return redirect()
            ->route('dashboard.equipment.index')
            ->with('status', "Perlengkapan {$equipment->name} berhasil dihapus.");
    }
}
