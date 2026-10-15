<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CreateOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    /**
     * Buat pesanan
     *
     * Membuat pesanan `Pending` beserta transaksi `Pending`, dengan satu atau lebih kendaraan (tiap kendaraan punya jadwal sendiri).
     * Kirim header `Idempotency-Key` agar permintaan ulang tidak membuat pesanan ganda: permintaan ulang dengan kunci yang sama
     * mengembalikan pesanan yang sudah dibuat dengan status 200. Hanya untuk pelanggan.
     */
    public function store(StoreCheckoutRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $order = $createOrder->handle($request->user(), $request->validated('items'), $request->idempotencyKey());

        return (new OrderResource($order))
            ->response()
            ->setStatusCode($order->wasRecentlyCreated ? 201 : 200);
    }
}
