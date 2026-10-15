<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Orders\ChangeOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeOrderStatusRequest;
use App\Http\Requests\OrderFilterRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\OrderStatus;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /**
     * Daftar pesanan
     *
     * Semua pesanan, terbaru di atas. Filter: `search` (kode, pelanggan, kendaraan), `status`, `type` (kategori kendaraan),
     * `from` dan `to` (rentang tanggal mulai sewa), serta paginasi.
     */
    public function index(OrderFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return OrderResource::collection(
            Order::query()
                ->filter($filters)
                ->with(['user', 'items.vehicle', 'transaction'])
                ->latest('id')
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
        );
    }

    /**
     * Detail pesanan
     */
    public function show(Order $order): OrderResource
    {
        Gate::authorize('manage', $order);

        return new OrderResource($order->load(['user', 'items.vehicle', 'transaction.bankAccount', 'cancellationRequests']));
    }

    /**
     * Ubah status pesanan
     *
     * `booking` menjadi `rented`, lalu `rented` menjadi `completed`. Perubahan lain ditolak (422).
     */
    public function status(ChangeOrderStatusRequest $request, Order $order, ChangeOrderStatus $changeOrderStatus): OrderResource
    {
        return new OrderResource($changeOrderStatus->handle($order, OrderStatus::from($request->validated('status'))));
    }
}
