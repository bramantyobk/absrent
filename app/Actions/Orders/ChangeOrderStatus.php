<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeOrderStatus
{
    /**
     * Alur status yang dikerjakan staf setelah Lunas: `Booking` menjadi `Disewa`, lalu `Disewa` menjadi `Selesai`.
     * Dicuri (hanya admin) dan inspeksi pada dialog ini menyusul.
     *
     * @var array<string, OrderStatus>
     */
    private const NEXT = [
        'booking' => OrderStatus::Rented,
        'rented' => OrderStatus::Completed,
    ];

    /**
     * @throws ValidationException
     */
    public function handle(Order $order, OrderStatus $to): Order
    {
        return DB::transaction(function () use ($order, $to): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ((self::NEXT[$locked->status->value] ?? null) !== $to) {
                throw ValidationException::withMessages([
                    'status' => "Status {$locked->status->label()} tidak dapat diubah menjadi {$to->label()}.",
                ]);
            }

            $locked->update(['status' => $to]);

            return $locked->load(['user', 'items.vehicle', 'transaction']);
        });
    }
}
