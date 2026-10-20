<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TermsPreviewRequest;
use App\Models\CompanyProfile;
use App\Models\Vehicle;
use App\Services\RentalQuote;
use App\Services\TermsTemplate;
use Illuminate\Http\JsonResponse;

class TermsController extends Controller
{
    /**
     * Syarat dan ketentuan
     *
     * Teks syarat dan ketentuan yang sudah terisi data pelanggan, kendaraan, dan periode calon pesanan (`start_at`, `days`,
     * `vehicle_ids[]`), untuk ditampilkan sebelum `POST /checkout` (yang mewajibkan `terms_accepted`). `text` berisi teks utuh dan
     * `blocks` memecahnya menjadi judul (`heading`) dan paragraf (`paragraph`). Hanya dibaca, tidak dapat diubah pelanggan.
     */
    public function show(TermsPreviewRequest $request, RentalQuote $rentalQuote, TermsTemplate $termsTemplate): JsonResponse
    {
        $vehicles = Vehicle::query()->whereIn('id', $request->vehicleIds())->get()->sortBy(
            fn (Vehicle $vehicle): int => array_search($vehicle->getKey(), $request->vehicleIds(), true)
        )->values();

        $startAt = RentalQuote::parse($request->validated('start_at'));
        $days = (int) $request->validated('days');
        $total = 0;

        foreach ($vehicles as $index => $vehicle) {
            $total += $rentalQuote->calculate($vehicle, $startAt, $days, "vehicle_ids.{$index}")['subtotal'];
        }

        return response()->json([
            'data' => $termsTemplate->forOrder(CompanyProfile::current(), $request->user(), $vehicles, $startAt, $days, $total),
        ]);
    }
}
