<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentProof;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderProofController extends Controller
{
    /**
     * Bukti pembayaran
     *
     * Bukti yang diunggah staf saat pesanan Lunas, hanya untuk pemilik pesanan (404 untuk pesanan orang lain atau bila belum ada).
     */
    public function __invoke(Order $order, PaymentProof $paymentProof): StreamedResponse
    {
        return $paymentProof->response($order->transaction()->firstOrFail());
    }
}
