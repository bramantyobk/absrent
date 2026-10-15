<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyTransaction
{
    public const PROOF_DIRECTORY = 'payment-proofs';

    /**
     * Staf menetapkan transaksi `Menunggu Verifikasi` menjadi Lunas (bukti wajib, pesanan menjadi `Booking`) atau
     * Gagal (catatan wajib, bersifat final: pesanan `Gagal` dan jadwal dilepas). Mencatat siapa dan kapan, dan staf
     * dapat mengoreksi rekening tujuan. Dipakai bersama Blade dan API.
     *
     * @throws ValidationException
     */
    public function handle(Transaction $transaction, User $verifier, TransactionStatus $status, ?UploadedFile $proof = null, ?string $note = null, ?int $bankAccountId = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $verifier, $status, $proof, $note, $bankAccountId): Transaction {
            $locked = Transaction::query()->whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
            $order = $locked->order()->lockForUpdate()->firstOrFail();

            if ($locked->status !== TransactionStatus::Verifying || $order->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Transaksi ini tidak sedang menunggu verifikasi.']);
            }

            $attributes = [
                'status' => $status,
                'verified_by' => $verifier->getKey(),
                'verified_at' => now(),
                'bank_account_id' => $bankAccountId ?? $locked->bank_account_id,
            ];

            if ($status === TransactionStatus::Paid) {
                $attributes['proof_path'] = $proof->store(self::PROOF_DIRECTORY);
                $order->update(['status' => OrderStatus::Booking, 'expires_at' => null]);
            } else {
                $attributes['note'] = $note;
                $order->update(['status' => OrderStatus::Failed, 'expires_at' => null]);
            }

            $locked->update($attributes);

            return $locked->load(['order.user', 'order.items.vehicle', 'bankAccount', 'verifier']);
        });
    }
}
