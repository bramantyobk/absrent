<?php

namespace App\Actions\Orders;

use App\InspectionType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditInspection
{
    /**
     * Admin menyunting inspeksi pengambilan atau pengembalian yang sudah tersimpan: odometer, level energi, jumlah perlengkapan yang
     * dicek (nama dan jumlah seharusnya pada snapshot tetap), dan catatan. Tanpa alasan wajib dan tanpa riwayat nilai lama, tetapi
     * pencatat (`edited_by`) dan waktunya (`edited_at`) disimpan sebagai jejak. Odometer tidak boleh melompati inspeksi lain pada unit
     * yang sama (menurut waktu inspeksi), dan `vehicles.odometer_km` ikut berubah hanya bila inspeksi ini yang terbaru untuk unit itu.
     * Inspeksi `manual` tidak disunting di sini (gunakan Perbarui odometer pada detail kendaraan). Dipakai bersama Blade dan API.
     *
     * @param  array{odometer_km: int|string, energy_level: int|string, note?: ?string, equipment?: ?array<int|string, int|string>}  $data
     *
     * @throws ValidationException
     */
    public function handle(VehicleInspection $inspection, User $admin, array $data): VehicleInspection
    {
        return DB::transaction(function () use ($inspection, $admin, $data): VehicleInspection {
            $locked = VehicleInspection::query()->whereKey($inspection->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->type === InspectionType::Manual) {
                throw ValidationException::withMessages(['inspection' => 'Inspeksi manual dikoreksi lewat Perbarui odometer pada detail kendaraan.']);
            }

            $vehicle = Vehicle::query()->whereKey($locked->vehicle_id)->lockForUpdate()->firstOrFail();
            $odometer = (int) $data['odometer_km'];

            $previous = $this->neighbour($locked, before: true);
            $next = $this->neighbour($locked, before: false);

            if ($previous && $odometer < $previous->odometer_km) {
                throw ValidationException::withMessages(['odometer_km' => $this->boundaryMessage('tidak boleh lebih kecil dari', $previous)]);
            }

            if ($next && $odometer > $next->odometer_km) {
                throw ValidationException::withMessages(['odometer_km' => $this->boundaryMessage('tidak boleh melebihi', $next)]);
            }

            $locked->update([
                'odometer_km' => $odometer,
                'energy_level' => (int) $data['energy_level'],
                'equipment_checks' => $this->equipmentChecks($locked, $data['equipment'] ?? []),
                'note' => $data['note'] ?? null,
                'edited_by' => $admin->getKey(),
                'edited_at' => now(),
            ]);

            if ($next === null) {
                $vehicle->update(['odometer_km' => $odometer]);
            }

            return $locked->load(['vehicle', 'orderItem.order']);
        });
    }

    /**
     * Inspeksi unit yang sama tepat sebelum atau sesudah inspeksi ini menurut waktu inspeksi (id sebagai pemisah).
     */
    private function neighbour(VehicleInspection $inspection, bool $before): ?VehicleInspection
    {
        $query = VehicleInspection::query()
            ->where('vehicle_id', $inspection->vehicle_id)
            ->whereKeyNot($inspection->getKey())
            ->where(fn (Builder $query) => $query
                ->where('inspected_at', $before ? '<' : '>', $inspection->inspected_at)
                ->orWhere(fn (Builder $query) => $query
                    ->where('inspected_at', $inspection->inspected_at)
                    ->where('id', $before ? '<' : '>', $inspection->getKey())));

        return $before
            ? $query->orderByDesc('inspected_at')->orderByDesc('id')->first()
            : $query->orderBy('inspected_at')->orderBy('id')->first();
    }

    private function boundaryMessage(string $relation, VehicleInspection $neighbour): string
    {
        return "Odometer {$relation} ".number_format($neighbour->odometer_km, 0, ',', '.')." km ({$neighbour->type->label()} unit ini pada {$neighbour->inspected_at->format('d/m/Y H:i')}).";
    }

    /**
     * Hanya jumlah yang dicek yang berubah, nama dan jumlah seharusnya mengikuti snapshot.
     *
     * @param  array<int|string, int|string>  $given  jumlah menurut id perlengkapan
     * @return list<array{equipment_item_id: int, name: string, expected: int, checked: int}>|null
     *
     * @throws ValidationException
     */
    private function equipmentChecks(VehicleInspection $inspection, array $given): ?array
    {
        $snapshot = $inspection->equipment_checks ?? [];

        $expectedIds = collect($snapshot)->pluck('equipment_item_id')->map(fn (int $id): string => (string) $id)->sort()->values()->all();
        $givenIds = collect(array_keys($given))->map(fn (int|string $id): string => (string) $id)->sort()->values()->all();

        if ($expectedIds !== $givenIds) {
            throw ValidationException::withMessages(['equipment' => 'Daftar perlengkapan harus sama dengan ceklis inspeksi ini. Muat ulang halaman lalu coba lagi.']);
        }

        if ($inspection->equipment_checks === null) {
            return null;
        }

        return array_map(fn (array $row): array => array_replace($row, ['checked' => (int) $given[$row['equipment_item_id']]]), $snapshot);
    }
}
