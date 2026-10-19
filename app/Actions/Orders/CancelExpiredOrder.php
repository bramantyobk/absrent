<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelExpiredOrder
{
    /**
     * Staf membatalkan sepihak pesanan yang batas waktunya sudah lewat, tanpa menunggu scheduler. Pesanan yang tidak dikonfirmasi
     * pelanggan melewati batas konfirmasi menjadi `Dibatalkan`. Pesanan yang sudah dikonfirmasi (Menunggu Verifikasi) tetapi tidak
     * diverifikasi melewati batas verifikasi menjadi `Gagal` (final, sama dengan scheduler). Keduanya dengan catatan pembatalnya,
     * dan jadwal kendaraan dilepas. Ditolak bila batas belum lewat atau pesanan berada pada status lain. Dipakai bersama Blade dan API.
     *
     * @throws ValidationException
     */
    public function handle(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $transaction = $locked->transaction()->lockForUpdate()->firstOrFail();

            $isUnconfirmed = $transaction->status === TransactionStatus::Pending;
            $isAwaitingVerification = $transaction->status === TransactionStatus::Verifying;

            if ($locked->status !== OrderStatus::Pending || (! $isUnconfirmed && ! $isAwaitingVerification)) {
                throw ValidationException::withMessages(['order' => 'Hanya pesanan yang menunggu konfirmasi atau verifikasi yang dapat dibatalkan dengan cara ini.']);
            }

            if ($locked->expires_at === null || $locked->expires_at->isFuture()) {
                throw ValidationException::withMessages(['order' => $isUnconfirmed ? 'Batas konfirmasi pesanan ini belum terlewati.' : 'Batas verifikasi pesanan ini belum terlewati.']);
            }

            if ($isUnconfirmed) {
                $note = "Dibatalkan oleh {$actor->name}: melewati batas konfirmasi";
                $transaction->update(['status' => TransactionStatus::Cancelled, 'note' => $note]);
                $locked->update(['status' => OrderStatus::Cancelled, 'status_note' => $note]);
            } else {
                $note = ExpireOrders::VERIFICATION_EXPIRED_NOTE." (digagalkan oleh {$actor->name})";
                $transaction->update(['status' => TransactionStatus::Failed, 'note' => $note]);
                $locked->update(['status' => OrderStatus::Failed, 'status_note' => $note]);
            }

            return $locked->load(['user', 'items.vehicle', 'transaction']);
        });
    }
}
