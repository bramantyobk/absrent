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

class HandleCancellationRequest
{
    /**
     * Staf menyetujui atau menolak pengajuan pembatalan. Persetujuan (satu langkah) menjadikan pesanan `Refund` dan
     * transaksi `Dibatalkan` sehingga jadwal dilepas, dan hanya dapat dilakukan selama pesanan masih `Booking`.
     * Dana dikembalikan manual di luar aplikasi, infonya dicatat di catatan keputusan. Penolakan tidak mengubah pesanan.
     *
     * @throws ValidationException
     */
    public function handle(CancellationRequest $cancellationRequest, User $handler, CancellationStatus $decision, string $note): CancellationRequest
    {
        return DB::transaction(function () use ($cancellationRequest, $handler, $decision, $note): CancellationRequest {
            $request = CancellationRequest::query()->whereKey($cancellationRequest->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== CancellationStatus::Pending) {
                throw ValidationException::withMessages(['decision' => 'Pengajuan ini sudah diputuskan.']);
            }

            if ($decision === CancellationStatus::Approved) {
                $order = Order::query()->whereKey($request->order_id)->lockForUpdate()->firstOrFail();

                if ($order->status !== OrderStatus::Booking) {
                    throw ValidationException::withMessages(['decision' => 'Pesanan sudah tidak berstatus Booking sehingga tidak dapat disetujui. Tolak pengajuan ini.']);
                }

                $order->transaction()->update(['status' => TransactionStatus::Cancelled]);
                $order->update(['status' => OrderStatus::Refunded]);
            }

            $request->update([
                'status' => $decision,
                'handled_by' => $handler->getKey(),
                'handled_note' => $note,
                'handled_at' => now(),
            ]);

            return $request->load(['order.user', 'order.items.vehicle', 'handler']);
        });
    }
}
