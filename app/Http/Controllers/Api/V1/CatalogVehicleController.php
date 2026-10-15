<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogFilterRequest;
use App\Http\Requests\RentalPeriodRequest;
use App\Http\Resources\CatalogVehicleResource;
use App\Models\Vehicle;
use App\Services\RentalQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogVehicleController extends Controller
{
    /**
     * Katalog kendaraan
     *
     * Terbuka tanpa login. Hanya unit yang tampil di katalog. Isi `start_at` dan `end_at` bersamaan untuk hanya menampilkan unit yang kosong pada rentang itu.
     */
    public function index(CatalogFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return CatalogVehicleResource::collection(
            Vehicle::query()
                ->listedInCatalog()
                ->catalogFilter($filters)
                ->orderBy('brand')
                ->orderBy('model')
                ->orderBy('id')
                ->paginate($filters['per_page'] ?? 12)
                ->withQueryString()
        );
    }

    /**
     * Detail kendaraan
     *
     * Beserta perlengkapan aktif sesuai kategorinya. Unit yang tidak tampil di katalog menghasilkan 404.
     */
    public function show(Vehicle $vehicle): CatalogVehicleResource
    {
        abort_unless($vehicle->is_available, 404);

        return new CatalogVehicleResource($vehicle->loadEquipment());
    }

    /**
     * Hitung sewa dan cek ketersediaan
     *
     * Mengembalikan jumlah hari dan subtotal bila jadwal valid dan unit kosong. Mulai tidak boleh di masa lalu,
     * durasi minimal 24 jam dan maksimal sesuai batas kendaraan (bawaan 7 hari). Jadwal yang melanggar aturan atau bentrok menghasilkan 422.
     */
    public function quote(RentalPeriodRequest $request, Vehicle $vehicle, RentalQuote $rentalQuote): JsonResponse
    {
        abort_unless($vehicle->is_available, 404);

        $quote = $rentalQuote->calculate($vehicle, $request->validated('start_at'), $request->validated('end_at'));
        $rentalQuote->ensureAvailable($vehicle, $quote['start_at'], $quote['end_at']);

        return response()->json(['data' => [
            'vehicle_id' => $vehicle->id,
            'start_at' => $quote['start_at'],
            'end_at' => $quote['end_at'],
            'days' => $quote['days'],
            'unit_price' => $quote['unit_price'],
            'subtotal' => $quote['subtotal'],
            'available' => true,
        ]]);
    }
}
