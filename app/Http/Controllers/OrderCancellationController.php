<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CancelOrder;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OrderCancellationController extends Controller
{
    public function store(Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        Gate::authorize('pay', $order->loadMissing('transaction'));

        $cancelOrder->handle($order);

        return redirect()
            ->route('orders.show', $order)
            ->with('status', "Pesanan {$order->code} dibatalkan.");
    }
}
