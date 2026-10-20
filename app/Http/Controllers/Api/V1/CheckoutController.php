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
     * Membuat pesanan `Pending` beserta transaksi `Pending`. Satu pesanan memakai satu periode (`start_at` dan `days`) untuk
     * semua kendaraannya, maksimal 2 kendaraan. `terms_accepted` wajib `true` (persetujuan syarat dan ketentuan, lihat `GET /terms`; tidak disimpan). `start_at` tanpa offset dibaca sebagai WIB, dengan offset dikonversi ke WIB.
     * Kirim header `Idempotency-Key` agar permintaan ulang tidak membuat pesanan ganda: permintaan ulang dengan kunci yang sama
     * mengembalikan pesanan yang sudah dibuat dengan status 200. Hanya untuk pelanggan.
     */
    public function store(StoreCheckoutRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $order = $createOrder->handle(
            $request->user(),
            $request->validated('start_at'),
            (int) $request->validated('days'),
            $request->vehicleIds(),
            $request->idempotencyKey(),
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode($order->wasRecentlyCreated ? 201 : 200);
    }
}
