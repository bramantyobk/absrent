<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /**
     * Pesanan saya
     *
     * Pesanan milik pelanggan yang login, terbaru di atas.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $request->user()->orders()->with(['items.vehicle', 'transaction'])->latest('id')->paginate(10)
        );
    }

    /**
     * Detail pesanan saya
     *
     * Pesanan milik pelanggan lain menghasilkan 403.
     */
    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(['items.vehicle', 'transaction', 'cancellationRequests']));
    }
}
