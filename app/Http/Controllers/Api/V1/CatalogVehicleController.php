<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogFilterRequest;
use App\Http\Requests\RentalPeriodRequest;
use App\Http\Requests\ReturnOptionsRequest;
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
     * Mengembalikan waktu selesai dan subtotal bila periode valid dan unit kosong. `start_at` tanpa offset dibaca sebagai
     * waktu lokasi perusahaan (WIB), dan dengan offset dikonversi ke WIB. Durasi `days` minimal 1 hari dan maksimal sesuai
     * batas kendaraan (bawaan 7 hari). Periode yang melanggar aturan atau bentrok (termasuk jeda pengembalian) menghasilkan 422.
     */
    public function quote(RentalPeriodRequest $request, Vehicle $vehicle, RentalQuote $rentalQuote): JsonResponse
    {
        abort_unless($vehicle->is_available, 404);

        $quote = $rentalQuote->calculate($vehicle, $request->validated('start_at'), (int) $request->validated('days'));
        $rentalQuote->ensureAvailable($vehicle, $quote['start_at'], $quote['end_at']);

        return response()->json(['data' => [
            'vehicle_id' => $vehicle->id,
            'start_at' => $quote['start_at']->toIso8601String(),
            'end_at' => $quote['end_at']->toIso8601String(),
            'end_label' => $rentalQuote->label($quote['end_at']),
            'days' => $quote['days'],
            'unit_price' => $quote['unit_price'],
            'subtotal' => $quote['subtotal'],
            'available' => true,
        ]]);
    }

    /**
     * Opsi durasi dan waktu pengembalian
     *
     * Untuk waktu mulai tertentu, daftar durasi yang dapat dipilih (1 hari sampai batas kendaraan). Durasi yang bentrok
     * dengan pesanan atau servis berikutnya (termasuk jeda pengembalian) tidak ikut. Daftar kosong berarti unit tidak
     * tersedia pada waktu mulai itu. Waktu mulai di masa lalu menghasilkan 422.
     */
    public function returnOptions(ReturnOptionsRequest $request, Vehicle $vehicle, RentalQuote $rentalQuote): JsonResponse
    {
        abort_unless($vehicle->is_available, 404);

        return response()->json(['data' => $rentalQuote->options($vehicle, $request->validated('start_at'))
            ->map(fn (array $option): array => [
                'days' => $option['days'],
                'end_at' => $option['end_at']->toIso8601String(),
                'end_label' => $option['end_label'],
                'subtotal' => $option['subtotal'],
            ])
            ->values()]);
    }
}
