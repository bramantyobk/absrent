<?php

namespace App\Services;

use App\InspectionType;
use App\Models\EquipmentItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\OrderStatus;
use Illuminate\Support\Collection;

/**
 * Menyiapkan isian dialog inspeksi di detail pesanan: tiap unit dengan odometer minimal, label energi, dan ceklis perlengkapan.
 */
class OrderInspectionForm
{
    /**
     * Inspeksi yang diminta oleh status berikutnya (pengambilan saat Booking, pengembalian saat Disewa), atau null.
     */
    public function typeFor(Order $order): ?InspectionType
    {
        return match ($order->status) {
            OrderStatus::Booking => InspectionType::Pickup,
            OrderStatus::Rented => InspectionType::Return,
            default => null,
        };
    }

    /**
     * @return Collection<int, array{item: OrderItem, minimum_odometer: int, energy_label: string, checklist: list<array{id: int, name: string, expected: int, default: int}>}>
     */
    public function for(Order $order, InspectionType $type): Collection
    {
        $order->loadMissing(['items.vehicle', 'items.inspections']);

        return $order->items->map(function (OrderItem $item) use ($type): array {
            $vehicle = $item->vehicle;
            $pickup = $item->inspections->firstWhere('type', InspectionType::Pickup);

            $checklist = $type === InspectionType::Pickup
                ? EquipmentItem::query()->active()->forCategory($vehicle->type)->ordered()->get()
                    ->map(fn (EquipmentItem $equipment): array => ['id' => $equipment->id, 'name' => $equipment->name, 'expected' => $equipment->quantity, 'default' => $equipment->quantity])
                    ->all()
                : collect($pickup?->equipment_checks ?? [])
                    ->map(fn (array $row): array => ['id' => $row['equipment_item_id'], 'name' => $row['name'], 'expected' => $row['expected'], 'default' => $row['checked']])
                    ->all();

            return [
                'item' => $item,
                'minimum_odometer' => max($vehicle->odometer_km ?? 0, $pickup?->odometer_km ?? 0),
                'energy_label' => ($vehicle->drive_type?->energyLabel()) ?? 'Bensin',
                'checklist' => $checklist,
            ];
        });
    }

    /**
     * Pesanan mendatang (Pending atau Booking) atas unit pada pesanan Dicuri, agar dapat diganti kendaraannya.
     *
     * @return Collection<int, OrderItem>
     */
    public function upcomingForStolen(Order $order): Collection
    {
        return OrderItem::query()
            ->whereIn('vehicle_id', $order->items->pluck('vehicle_id'))
            ->where('start_at', '>', now())
            ->whereHas('order', fn ($query) => $query->whereIn('status', [OrderStatus::Pending, OrderStatus::Booking]))
            ->with(['order.user', 'vehicle'])
            ->orderBy('start_at')
            ->get();
    }
}
