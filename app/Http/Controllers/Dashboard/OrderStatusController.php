<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Orders\ChangeOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeOrderStatusRequest;
use App\Models\Order;
use App\OrderStatus;
use Illuminate\Http\RedirectResponse;

class OrderStatusController extends Controller
{
    public function update(ChangeOrderStatusRequest $request, Order $order, ChangeOrderStatus $changeOrderStatus): RedirectResponse
    {
        $order = $changeOrderStatus->handle(
            $order,
            OrderStatus::from($request->validated('status')),
            $request->user(),
            $request->inspections(),
            $request->validated('note'),
        );

        return redirect()
            ->route('dashboard.orders.show', $order)
            ->with('status', "Status pesanan {$order->code} diubah menjadi {$order->status->label()}.");
    }
}
