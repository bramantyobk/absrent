<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Vehicles\CreateVehicle;
use App\Actions\Vehicles\DeleteVehicle;
use App\Actions\Vehicles\UpdateVehicle;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Requests\VehicleFilterRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VehicleController extends Controller
{
    /**
     * Daftar kendaraan
     *
     * Admin dan operator. Mendukung pencarian (merek, model, plat), filter tipe dan ketersediaan, serta paginasi.
     */
    public function index(VehicleFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return VehicleResource::collection(
            Vehicle::query()
                ->filter($filters)
                ->orderBy('brand')
                ->orderBy('model')
                ->orderBy('id')
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
        );
    }

    /**
     * Detail kendaraan
     */
    public function show(Vehicle $vehicle): VehicleResource
    {
        Gate::authorize('view', $vehicle);

        return new VehicleResource($vehicle);
    }

    /**
     * Tambah kendaraan
     *
     * Kirim sebagai `multipart/form-data` bila menyertakan foto (JPG/PNG/WebP, maksimal 1 MB).
     */
    public function store(StoreVehicleRequest $request, CreateVehicle $createVehicle): VehicleResource
    {
        $vehicle = $createVehicle->handle($request->vehicleAttributes(), $request->file('photo'));

        /** @status 201 */
        return new VehicleResource($vehicle);
    }

    /**
     * Ubah kendaraan
     *
     * PHP tidak membaca body `multipart/form-data` pada PUT/PATCH. Untuk mengirim foto, gunakan `POST` dengan field `_method=PUT`.
     * Set `remove_photo=1` untuk menghapus foto tanpa menggantinya.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, UpdateVehicle $updateVehicle): VehicleResource
    {
        return new VehicleResource($updateVehicle->handle(
            $vehicle,
            $request->vehicleAttributes(),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        ));
    }

    /**
     * Hapus kendaraan
     *
     * Ditolak dengan 409 bila kendaraan sudah pernah dipesan.
     */
    public function destroy(Vehicle $vehicle, DeleteVehicle $deleteVehicle): Response|JsonResponse
    {
        Gate::authorize('delete', $vehicle);

        if (! $deleteVehicle->handle($vehicle)) {
            return response()->json([
                'message' => 'Kendaraan memiliki riwayat pesanan. Sembunyikan dari katalog sebagai gantinya.',
            ], 409);
        }

        /** @status 204 */
        return response()->noContent();
    }
}
