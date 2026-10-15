<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\RequestOrderCancellation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCancellationRequestRequest;
use App\Http\Resources\CancellationRequestResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderCancellationRequestController extends Controller
{
    /**
     * Ajukan pembatalan
     *
     * Hanya untuk pesanan Lunas yang masih `booking` dan belum punya pengajuan yang menunggu (403 bila tidak). Mengisi `reason`.
     * Pesanan tidak berubah sampai operator memutuskan.
     */
    public function store(StoreCancellationRequestRequest $request, Order $order, RequestOrderCancellation $requestOrderCancellation): JsonResponse
    {
        $cancellationRequest = $requestOrderCancellation->handle($order, $request->user(), $request->validated('reason'));

        return (new CancellationRequestResource($cancellationRequest))->response()->setStatusCode(201);
    }
}
