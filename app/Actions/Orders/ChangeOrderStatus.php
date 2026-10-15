<?php

namespace App\Actions\Orders;

use App\InspectionType;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\Services\InspectionRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeOrderStatus
{
    public function __construct(private InspectionRecorder $inspections) {}

    /**
     * Alur status yang dikerjakan staf setelah Lunas: `Booking` menjadi `Disewa` (inspeksi pengambilan), lalu `Disewa`
     * menjadi `Selesai` (inspeksi pengembalian) atau `Dicuri` (hanya admin, tanpa inspeksi, catatan wajib).
     *
     * @var array<string, list<OrderStatus>>
     */
    private const NEXT = [
        'booking' => [OrderStatus::Rented],
        'rented' => [OrderStatus::Completed, OrderStatus::Stolen],
    ];

    /**
     * Mengubah status dan menyimpan inspeksi tiap unit dalam satu proses yang tidak terpisah: bila ada inspeksi yang kosong
     * atau tidak valid, status tidak berubah. Inspeksi memperbarui odometer kendaraan. Dicuri menahan unit permanen
     * dengan menyembunyikannya dari katalog (`is_available` false). Dipakai bersama Blade dan API.
     *
     * @param  array<int|string, array{odometer_km: int|string, energy_level: int|string, note?: ?string, equipment?: ?array<int|string, int|string>}>  $inspections  menurut id item pesanan
     *
     * @throws ValidationException
     */
    public function handle(Order $order, OrderStatus $to, User $actor, array $inspections = [], ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $inspections, $note): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($to, self::NEXT[$locked->status->value] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Status {$locked->status->label()} tidak dapat diubah menjadi {$to->label()}.",
                ]);
            }

            $items = $locked->items()->orderBy('id')->get();
            $vehicles = Vehicle::query()->whereIn('id', $items->pluck('vehicle_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($to === OrderStatus::Stolen) {
                $vehicles->each->update(['is_available' => false]);
                $locked->update(['status' => $to, 'status_note' => $note]);

                return $locked->load(['user', 'items.vehicle', 'transaction']);
            }

            $type = $to === OrderStatus::Rented ? InspectionType::Pickup : InspectionType::Return;
            $this->ensureEveryUnitIsInspected($items, $inspections);

            foreach ($items as $item) {
                $this->inspections->record($item, $vehicles[$item->vehicle_id], $type, $inspections[$item->getKey()], $actor, "inspections.{$item->getKey()}");
            }

            $locked->update(['status' => $to]);

            return $locked->load(['user', 'items.vehicle', 'transaction']);
        });
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @param  array<int|string, mixed>  $inspections
     *
     * @throws ValidationException
     */
    private function ensureEveryUnitIsInspected($items, array $inspections): void
    {
        $expected = $items->pluck('id')->map(fn (int $id): string => (string) $id)->sort()->values()->all();
        $given = collect(array_keys($inspections))->map(fn (int|string $id): string => (string) $id)->sort()->values()->all();

        if ($expected !== $given) {
            throw ValidationException::withMessages(['inspections' => 'Inspeksi harus diisi untuk setiap kendaraan pada pesanan ini.']);
        }
    }
}
