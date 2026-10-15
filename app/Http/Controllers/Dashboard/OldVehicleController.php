<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Orders\ResolveOldVehicle;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveOldVehicleRequest;
use App\Models\OrderVehicleChange;
use App\OldVehicleStatus;
use Illuminate\Http\RedirectResponse;

class OldVehicleController extends Controller
{
    public function update(ResolveOldVehicleRequest $request, OrderVehicleChange $change, ResolveOldVehicle $resolveOldVehicle): RedirectResponse
    {
        $resolved = $resolveOldVehicle->handle(
            $change,
            $request->user(),
            OldVehicleStatus::from($request->validated('action')),
            $request->validated('inspection') ?? [],
            $request->validated('note'),
        );

        return redirect()
            ->route('dashboard.orders.show', $resolved->orderItem->order_id)
            ->with('status', "Unit lama {$resolved->fromVehicle->brand} {$resolved->fromVehicle->model} ditandai {$resolved->old_vehicle_status->label()}.");
    }
}
