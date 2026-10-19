<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Orders\EditInspection;
use App\Http\Controllers\Controller;
use App\Http\Requests\EditInspectionRequest;
use App\Http\Resources\VehicleInspectionResource;
use App\Models\VehicleInspection;

class InspectionController extends Controller
{
    /**
     * Sunting inspeksi
     *
     * Khusus admin (operator 403). Mengubah `odometer_km`, `energy_level` (0-100), `note`, dan `equipment` (jumlah yang dicek menurut id
     * perlengkapan, harus sama dengan ceklis inspeksi). Odometer tidak boleh melompati inspeksi lain pada unit yang sama, dan odometer kendaraan
     * ikut berubah hanya bila inspeksi ini yang terbaru untuk unit itu. Inspeksi `manual` ditolak (422).
     */
    public function update(EditInspectionRequest $request, VehicleInspection $inspection, EditInspection $editInspection): VehicleInspectionResource
    {
        return new VehicleInspectionResource($editInspection->handle($inspection, $request->user(), $request->validated()));
    }
}
