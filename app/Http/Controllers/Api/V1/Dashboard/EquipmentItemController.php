<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Equipment\CreateEquipmentItem;
use App\Actions\Equipment\UpdateEquipmentItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipmentItemFilterRequest;
use App\Http\Requests\StoreEquipmentItemRequest;
use App\Http\Requests\UpdateEquipmentItemRequest;
use App\Http\Resources\EquipmentItemResource;
use App\Models\EquipmentItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EquipmentItemController extends Controller
{
    /**
     * Daftar perlengkapan
     *
     * Master perlengkapan per kategori (motor/mobil). Mendukung pencarian nama, filter kategori dan status, serta paginasi.
     */
    public function index(EquipmentItemFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return EquipmentItemResource::collection(
            EquipmentItem::query()
                ->filter($filters)
                ->orderBy('category')
                ->ordered()
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
        );
    }

    /**
     * Detail perlengkapan
     */
    public function show(EquipmentItem $equipment): EquipmentItemResource
    {
        Gate::authorize('view', $equipment);

        return new EquipmentItemResource($equipment);
    }

    /**
     * Tambah perlengkapan
     *
     * Urutan yang dikosongkan ditempatkan di akhir daftar kategorinya.
     */
    public function store(StoreEquipmentItemRequest $request, CreateEquipmentItem $createEquipmentItem): EquipmentItemResource
    {
        /** @status 201 */
        return new EquipmentItemResource($createEquipmentItem->handle($request->validated()));
    }

    /**
     * Ubah perlengkapan
     *
     * Perlengkapan tidak dihapus. Nonaktifkan dengan `is_active=false`, dan item nonaktif tidak muncul pada inspeksi baru.
     */
    public function update(UpdateEquipmentItemRequest $request, EquipmentItem $equipment, UpdateEquipmentItem $updateEquipmentItem): EquipmentItemResource
    {
        return new EquipmentItemResource($updateEquipmentItem->handle($equipment, $request->validated()));
    }
}
