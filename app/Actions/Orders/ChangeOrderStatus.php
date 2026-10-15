<?php

namespace App\Actions\Orders;

use App\InspectionType;
use App\Models\EquipmentItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\OrderStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeOrderStatus
{
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
                $this->inspect($item, $vehicles[$item->vehicle_id], $type, $inspections[$item->getKey()], $actor);
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

    /**
     * @param  array{odometer_km: int|string, energy_level: int|string, note?: ?string, equipment?: ?array<int|string, int|string>}  $input
     *
     * @throws ValidationException
     */
    private function inspect(OrderItem $item, Vehicle $vehicle, InspectionType $type, array $input, User $actor): VehicleInspection
    {
        $key = "inspections.{$item->getKey()}";
        $odometer = (int) $input['odometer_km'];
        $pickup = $type === InspectionType::Return ? $item->inspections()->where('type', InspectionType::Pickup)->latest('id')->first() : null;

        if ($type === InspectionType::Return && ($item->inspections()->where('type', InspectionType::Return)->exists())) {
            throw ValidationException::withMessages(["{$key}.odometer_km" => 'Inspeksi pengembalian unit ini sudah tercatat.']);
        }

        $minimum = max($vehicle->odometer_km ?? 0, $pickup?->odometer_km ?? 0);

        if ($odometer < $minimum) {
            throw ValidationException::withMessages(["{$key}.odometer_km" => "Odometer tidak boleh lebih kecil dari {$minimum} km."]);
        }

        $inspection = $item->inspections()->create([
            'vehicle_id' => $vehicle->getKey(),
            'type' => $type,
            'odometer_km' => $odometer,
            'energy_level' => (int) $input['energy_level'],
            'equipment_checks' => $this->equipmentChecks($item, $vehicle, $type, $pickup, $input['equipment'] ?? [], $key),
            'note' => $input['note'] ?? null,
            'inspected_by' => $actor->getKey(),
            'inspected_at' => now(),
        ]);

        $vehicle->update(['odometer_km' => $odometer]);

        return $inspection;
    }

    /**
     * Pengambilan membuat snapshot dari perlengkapan aktif sesuai kategori unit. Pengembalian memakai snapshot pengambilan
     * unit yang sama (bukan master terkini) dan hanya mengisi jumlah yang kembali.
     *
     * @param  array<int|string, int|string>  $given  jumlah yang dicek menurut id perlengkapan
     * @return list<array{equipment_item_id: int, name: string, expected: int, checked: int}>
     *
     * @throws ValidationException
     */
    private function equipmentChecks(OrderItem $item, Vehicle $vehicle, InspectionType $type, ?VehicleInspection $pickup, array $given, string $key): array
    {
        $checklist = $type === InspectionType::Pickup
            ? EquipmentItem::query()->active()->forCategory($vehicle->type)->ordered()->get()
                ->map(fn (EquipmentItem $equipment): array => ['equipment_item_id' => $equipment->id, 'name' => $equipment->name, 'expected' => $equipment->quantity])
                ->all()
            : collect($pickup?->equipment_checks ?? [])
                ->map(fn (array $row): array => ['equipment_item_id' => $row['equipment_item_id'], 'name' => $row['name'], 'expected' => $row['expected']])
                ->all();

        $expectedIds = collect($checklist)->pluck('equipment_item_id')->map(fn (int $id): string => (string) $id)->sort()->values()->all();
        $givenIds = collect(array_keys($given))->map(fn (int|string $id): string => (string) $id)->sort()->values()->all();

        if ($expectedIds !== $givenIds) {
            throw ValidationException::withMessages(["{$key}.equipment" => 'Daftar perlengkapan berubah. Muat ulang halaman lalu isi kembali ceklis perlengkapan.']);
        }

        return array_map(fn (array $row): array => $row + ['checked' => (int) $given[$row['equipment_item_id']]], $checklist);
    }
}
