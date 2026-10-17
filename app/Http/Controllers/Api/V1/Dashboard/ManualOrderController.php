<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Orders\CreateManualOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;

class ManualOrderController extends Controller
{
    /**
     * Buat pesanan manual
     *
     * Booking langsung untuk pelanggan di kantor. Pelanggan: `customer_id` (yang ada) atau `new_customer` (`name`, `phone`, `email` wajib, role `user`,
     * password acak). Nomor yang sama dengan nama yang sama ditolak, nomor yang sama dengan nama berbeda menandai pelanggan lama (`phone_flagged`).
     * Satu periode (`start_at`, `days`) dengan maksimal 2 kendaraan (`items`), aturan bentrok dan durasi sama dengan pesanan online. Pembayaran dicatat
     * langsung Lunas: `bank_account_id`, `payment_note`, dan `proof` (JPG/PNG maksimal 2 MB, multipart) opsional. Pesanan berstatus `booking`.
     */
    public function store(StoreManualOrderRequest $request, CreateManualOrder $createManualOrder): JsonResponse
    {
        $order = $createManualOrder->handle($request->user(), $request->orderData(), $request->file('proof'));

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}
