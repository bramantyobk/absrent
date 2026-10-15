<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Orders\ChangeOrderVehicle;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeOrderVehicleRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;

class OrderVehicleChangeController extends Controller
{
    public function store(ChangeOrderVehicleRequest $request, Order $order, OrderItem $item, ChangeOrderVehicle $changeOrderVehicle): RedirectResponse
    {
        $change = $changeOrderVehicle->handle($order, $item, $request->user(), $request->changeData());

        return redirect()
            ->route('dashboard.orders.show', $order)
            ->with('status', "Kendaraan diganti dari {$change->fromVehicle->brand} {$change->fromVehicle->model} ke {$change->toVehicle->brand} {$change->toVehicle->model}.");
    }
}
