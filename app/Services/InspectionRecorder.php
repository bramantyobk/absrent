<?php

namespace App\Services;

use App\InspectionType;
use App\Models\EquipmentItem;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat inspeksi pengambilan atau pengembalian satu unit pada item pesanan, dan memperbarui odometer kendaraan.
 * Dipakai ubah status pesanan dan ganti kendaraan. Pemanggil harus sudah berada dalam transaksi dan mengunci baris kendaraan.
 */
class InspectionRecorder
{
    /**
     * @param  array{odometer_km: int|string, energy_level: int|string, note?: ?string, equipment?: ?array<int|string, int|string>}  $input
     * @param  string  $key  awalan kunci kesalahan validasi, mis. `inspections.12`
     *
     * @throws ValidationException
     */
    public function record(OrderItem $item, Vehicle $vehicle, InspectionType $type, array $input, User $actor, string $key): VehicleInspection
    {
        $pickup = $type === InspectionType::Return ? $this->pickupOf($item, $vehicle) : null;

        if ($type === InspectionType::Return && $item->inspections()->where('vehicle_id', $vehicle->getKey())->where('type', InspectionType::Return)->exists()) {
            throw ValidationException::withMessages(["{$key}.odometer_km" => 'Inspeksi pengembalian unit ini sudah tercatat.']);
        }

        $odometer = (int) $input['odometer_km'];
        $minimum = max($vehicle->odometer_km ?? 0, $pickup?->odometer_km ?? 0);

        if ($odometer < $minimum) {
            throw ValidationException::withMessages(["{$key}.odometer_km" => "Odometer tidak boleh lebih kecil dari {$minimum} km."]);
        }

        $inspection = $item->inspections()->create([
            'vehicle_id' => $vehicle->getKey(),
            'type' => $type,
            'odometer_km' => $odometer,
            'energy_level' => (int) $input['energy_level'],
            'equipment_checks' => $this->equipmentChecks($vehicle, $type, $pickup, $input['equipment'] ?? [], $key),
            'note' => $input['note'] ?? null,
            'inspected_by' => $actor->getKey(),
            'inspected_at' => now(),
        ]);

        $vehicle->update(['odometer_km' => $odometer]);

        return $inspection;
    }

    /**
     * Inspeksi pengambilan unit ini pada item tersebut (sebuah item dapat berpindah unit lewat ganti kendaraan).
     */
    public function pickupOf(OrderItem $item, Vehicle $vehicle): ?VehicleInspection
    {
        return $item->inspections()->where('vehicle_id', $vehicle->getKey())->where('type', InspectionType::Pickup)->latest('id')->first();
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
    private function equipmentChecks(Vehicle $vehicle, InspectionType $type, ?VehicleInspection $pickup, array $given, string $key): array
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
