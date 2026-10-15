<?php

namespace App\Http\Controllers;

use App\Actions\Orders\RequestOrderCancellation;
use App\Http\Requests\StoreCancellationRequestRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class OrderCancellationRequestController extends Controller
{
    public function store(StoreCancellationRequestRequest $request, Order $order, RequestOrderCancellation $requestOrderCancellation): RedirectResponse
    {
        $requestOrderCancellation->handle($order, $request->user(), $request->validated('reason'));

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Pengajuan pembatalan terkirim. Operator akan menghubungi Anda.');
    }
}
