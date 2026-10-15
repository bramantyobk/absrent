<?php

namespace App\Actions\Orders;

use App\CancellationStatus;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestOrderCancellation
{
    /**
     * Pelanggan mengajukan pembatalan pesanan Lunas yang belum Disewa, dengan alasan. Pesanan tidak berubah sampai
     * staf memutuskan. Hanya satu pengajuan menunggu per pesanan.
     *
     * @throws ValidationException
     */
    public function handle(Order $order, User $customer, string $reason): CancellationRequest
    {
        return DB::transaction(function () use ($order, $customer, $reason): CancellationRequest {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $isPaidBooking = $locked->status === OrderStatus::Booking && $locked->transaction?->status === TransactionStatus::Paid;
            $hasPending = $locked->cancellationRequests()->where('status', CancellationStatus::Pending)->exists();

            if (! $isPaidBooking || $hasPending) {
                throw ValidationException::withMessages(['order' => 'Pengajuan pembatalan tidak dapat dibuat untuk pesanan ini.']);
            }

            return $locked->cancellationRequests()->create([
                'user_id' => $customer->getKey(),
                'reason' => $reason,
                'status' => CancellationStatus::Pending,
            ]);
        });
    }
}
