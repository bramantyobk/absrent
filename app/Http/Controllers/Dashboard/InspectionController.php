<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Orders\EditInspection;
use App\Http\Controllers\Controller;
use App\Http\Requests\EditInspectionRequest;
use App\Models\VehicleInspection;
use Illuminate\Http\RedirectResponse;

class InspectionController extends Controller
{
    public function update(EditInspectionRequest $request, VehicleInspection $inspection, EditInspection $editInspection): RedirectResponse
    {
        $edited = $editInspection->handle($inspection, $request->user(), $request->validated());

        return redirect()
            ->route('dashboard.orders.show', $edited->orderItem->order_id)
            ->with('status', "Inspeksi {$edited->type->label()} {$edited->vehicle->brand} {$edited->vehicle->model} diperbarui.");
    }
}
