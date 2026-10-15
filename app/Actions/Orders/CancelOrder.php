<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    /**
     * Pelanggan membatalkan pesanan yang belum dikonfirmasi pembayarannya. Pesanan dan transaksi menjadi `Dibatalkan`,
     * sehingga jadwal kendaraan dilepas. Setelah konfirmasi atau Lunas, pembatalan tidak dapat dilakukan sepihak.
     *
     * @throws ValidationException
     */
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $transaction = $locked->transaction()->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Pending || $transaction->status !== TransactionStatus::Pending) {
                throw ValidationException::withMessages(['order' => 'Pesanan ini sudah dikonfirmasi sehingga tidak dapat dibatalkan langsung.']);
            }

            $transaction->update(['status' => TransactionStatus::Cancelled]);
            $locked->update(['status' => OrderStatus::Cancelled]);

            return $locked->load(['items.vehicle', 'transaction']);
        });
    }
}
