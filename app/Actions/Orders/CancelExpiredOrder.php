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
     * Staf membatalkan sepihak pesanan yang tidak dikonfirmasi pelanggan dan sudah melewati batas konfirmasi, tanpa menunggu
     * scheduler. Pesanan dan transaksi menjadi `Dibatalkan` dengan catatan pembatalnya, sehingga jadwal kendaraan dilepas.
     * Ditolak bila batas belum lewat atau pelanggan sudah mengonfirmasi (itu urusan verifikasi). Dipakai bersama Blade dan API.
     *
     * @throws ValidationException
     */
    public function handle(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $transaction = $locked->transaction()->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Pending || $transaction->status !== TransactionStatus::Pending) {
                throw ValidationException::withMessages(['order' => 'Hanya pesanan yang belum dikonfirmasi pelanggan yang dapat dibatalkan dengan cara ini.']);
            }

            if ($locked->expires_at === null || $locked->expires_at->isFuture()) {
                throw ValidationException::withMessages(['order' => 'Batas konfirmasi pesanan ini belum terlewati.']);
            }

            $note = "Dibatalkan oleh {$actor->name}: melewati batas konfirmasi";

            $transaction->update(['status' => TransactionStatus::Cancelled, 'note' => $note]);
            $locked->update(['status' => OrderStatus::Cancelled, 'status_note' => $note]);

            return $locked->load(['user', 'items.vehicle', 'transaction']);
        });
    }
}
