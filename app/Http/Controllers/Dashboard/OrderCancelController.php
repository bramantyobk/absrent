<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Orders\CancelExpiredOrder;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderCancelController extends Controller
{
    public function store(Request $request, Order $order, CancelExpiredOrder $cancelExpiredOrder): RedirectResponse
    {
        Gate::authorize('manage', $order);

        $order = $cancelExpiredOrder->handle($order, $request->user());

        return redirect()
            ->route('dashboard.orders.show', $order)
            ->with('status', $order->status === OrderStatus::Failed
                ? "Pesanan {$order->code} digagalkan karena batas verifikasi terlewati."
                : "Pesanan {$order->code} dibatalkan."
            );
    }
}
