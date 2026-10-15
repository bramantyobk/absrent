<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Support\Facades\DB;

class ExpireOrders
{
    public const VERIFICATION_EXPIRED_NOTE = 'Verifikasi tidak dilakukan dalam batas waktu';

    /**
     * Dipanggil scheduler. Pesanan `Pending` yang tidak dikonfirmasi sampai `expires_at` menjadi `Dibatalkan`, dan
     * transaksi `Menunggu Verifikasi` yang tidak diverifikasi sampai `expires_at` menjadi `Gagal` beserta pesanannya.
     * Keduanya melepas jadwal kendaraan.
     *
     * @return array{cancelled: int, failed: int}
     */
    public function handle(): array
    {
        return [
            'cancelled' => $this->expire(TransactionStatus::Pending, OrderStatus::Cancelled, TransactionStatus::Cancelled),
            'failed' => $this->expire(TransactionStatus::Verifying, OrderStatus::Failed, TransactionStatus::Failed, self::VERIFICATION_EXPIRED_NOTE),
        ];
    }

    private function expire(TransactionStatus $from, OrderStatus $orderTo, TransactionStatus $transactionTo, ?string $note = null): int
    {
        $count = 0;

        Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('expires_at', '<=', now())
            ->whereHas('transaction', fn ($query) => $query->where('status', $from))
            ->lazyById(100)
            ->each(function (Order $order) use ($from, $orderTo, $transactionTo, $note, &$count): void {
                $count += (int) DB::transaction(function () use ($order, $from, $orderTo, $transactionTo, $note): bool {
                    $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
                    $transaction = $locked?->transaction()->lockForUpdate()->first();

                    if (! $locked || ! $transaction || $locked->status !== OrderStatus::Pending || $transaction->status !== $from || $locked->expires_at?->isFuture()) {
                        return false;
                    }

                    $transaction->update(['status' => $transactionTo, 'note' => $note ?? $transaction->note]);
                    $locked->update(['status' => $orderTo]);

                    return true;
                });
            });

        return $count;
    }
}
