<?php

namespace App\Actions\Orders;

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\TransactionStatus;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmOrderPayment
{
    /**
     * Konfirmasi pembayaran oleh pelanggan: hanya dari `Pending` dan hanya sekali. Menyimpan rekening tujuan, menetapkan
     * operator aktif dengan penugasan terlama (rotator, kosong bila tidak ada operator aktif sehingga tautan jatuh ke
     * nomor support), mengubah transaksi menjadi `Menunggu Verifikasi`, dan memulai batas verifikasi. Dipakai bersama
     * Blade dan API.
     *
     * @throws ValidationException
     */
    public function handle(Order $order, int $bankAccountId): Order
    {
        return DB::transaction(function () use ($order, $bankAccountId): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $transaction = $locked->transaction()->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Pending || $transaction->status !== TransactionStatus::Pending) {
                throw ValidationException::withMessages(['order' => 'Pembayaran pesanan ini sudah dikonfirmasi atau pesanan tidak dapat dibayar lagi.']);
            }

            if ($locked->expires_at?->isPast()) {
                throw ValidationException::withMessages(['order' => 'Batas pembayaran pesanan ini sudah terlewati.']);
            }

            $bankAccount = BankAccount::query()->active()->find($bankAccountId);

            if (! $bankAccount) {
                throw ValidationException::withMessages(['bank_account_id' => 'Rekening tujuan tidak tersedia. Pilih rekening lain.']);
            }

            $operator = $this->nextOperator();
            $confirmedAt = now();

            $transaction->update([
                'bank_account_id' => $bankAccount->getKey(),
                'confirmed_at' => $confirmedAt,
                'status' => TransactionStatus::Verifying,
            ]);

            $locked->update([
                'assigned_operator_id' => $operator?->getKey(),
                'expires_at' => $confirmedAt->copy()->addHours(config('rental.confirm_validation_window_hours')),
            ]);

            $operator?->update(['last_assigned_at' => $confirmedAt]);

            return $locked->load(['user', 'items.vehicle', 'transaction.bankAccount', 'assignedOperator']);
        });
    }

    /**
     * Operator aktif dan bertugas dengan penugasan terlama (yang belum pernah ditugaskan didahulukan).
     */
    private function nextOperator(): ?User
    {
        return User::query()
            ->where('role', UserRole::Operator)
            ->where('is_active', true)
            ->where('is_on_duty', true)
            ->orderByRaw('last_assigned_at is not null')
            ->orderBy('last_assigned_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
