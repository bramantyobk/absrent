<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, Transaction $transaction): bool
    {
        return $actor->isStaff();
    }

    /**
     * Admin dan operator memverifikasi pembayaran (Lunas atau Gagal).
     */
    public function verify(User $actor, Transaction $transaction): bool
    {
        return $actor->isStaff();
    }

    /**
     * Bukti pembayaran hanya dapat dilihat staf dan pemilik pesanan. Pemilik pesanan lain dijawab 404.
     */
    public function viewProof(User $actor, Transaction $transaction): Response
    {
        if ($actor->isStaff() || $actor->is($transaction->order->user)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}
