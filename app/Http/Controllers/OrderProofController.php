<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentProof;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderProofController extends Controller
{
    /**
     * Bukti pembayaran pesanan milik pelanggan sendiri (diunggah staf saat Lunas).
     */
    public function __invoke(Order $order, PaymentProof $paymentProof): StreamedResponse
    {
        return $paymentProof->response($order->transaction()->firstOrFail());
    }
}
