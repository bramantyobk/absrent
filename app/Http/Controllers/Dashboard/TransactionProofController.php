<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PaymentProof;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionProofController extends Controller
{
    public function __invoke(Transaction $transaction, PaymentProof $paymentProof): StreamedResponse
    {
        return $paymentProof->response($transaction);
    }
}
