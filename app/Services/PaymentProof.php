<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Melayani bukti pembayaran dari disk privat setelah otorisasi (staf atau pemilik pesanan).
 */
class PaymentProof
{
    public function response(Transaction $transaction): StreamedResponse
    {
        $transaction->loadMissing('order.user');

        Gate::authorize('viewProof', $transaction);

        abort_unless($transaction->proof_path && Storage::exists($transaction->proof_path), 404);

        return Storage::response($transaction->proof_path, null, [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }
}
