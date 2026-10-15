<?php

namespace App\Actions\Orders;

use App\InspectionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderVehicleChange;
use App\Models\User;
use App\Models\Vehicle;
use App\OldVehicleStatus;
use App\OrderStatus;
use App\Services\InspectionRecorder;
use App\Services\ReplacementOptions;
use App\VehicleChangeReason;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeOrderVehicle
{
    public function __construct(private InspectionRecorder $inspections, private ReplacementOptions $replacements) {}

    /**
     * Mengganti kendaraan pada satu item pesanan `Booking` atau `Disewa`. Rentang sewa dan `unit_price`/`subtotal` item tidak
     * berubah, selisih biaya (bertanda, diinput staf) dicatat di riwayat dan ditambahkan ke `total_price`. Unit pengganti harus
     * kosong, tampil di katalog, dan sekategori kecuali alasan Permintaan Pelanggan. Dari `Disewa`, unit lama ditandai Sudah diinspeksi
     * (inspeksi pengembalian), Ditunda (tetap menahan jadwal), atau Hilang/dicuri (disembunyikan dari katalog), dan unit baru diinspeksi
     * pengambilan. Dari `Booking` tidak ada inspeksi. Semua dalam satu transaksi dengan kunci baris kendaraan (urut id).
     *
     * @param  array{vehicle_id: int|string, reason: string, note?: ?string, final_amount: int|string, old_vehicle?: array<string, mixed>, pickup?: array<string, mixed>}  $data
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function handle(Order $order, OrderItem $item, User $actor, array $data): OrderVehicleChange
    {
        return DB::transaction(function () use ($order, $item, $actor, $data): OrderVehicleChange {
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->status, [OrderStatus::Booking, OrderStatus::Rented], true)) {
                throw ValidationException::withMessages(['order' => 'Kendaraan hanya dapat diganti pada pesanan Booking atau Disewa.']);
            }

            $lockedItem = $lockedOrder->items()->whereKey($item->getKey())->firstOrFail();
            $lockedItem->setRelation('order', $lockedOrder);

            $vehicles = Vehicle::query()->whereIn('id', [$lockedItem->vehicle_id, (int) $data['vehicle_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $old = $vehicles[$lockedItem->vehicle_id];
            $new = $vehicles->get((int) $data['vehicle_id']);
            $reason = VehicleChangeReason::from($data['reason']);

            $this->ensureReplacementIsValid($lockedItem, $old, $new, $reason);

            $finalAmount = (int) $data['final_amount'];
            $this->ensureAmountIsAllowed($lockedOrder, $actor, $finalAmount);

            $oldStatus = null;
            $oldNote = null;
            $oldInspectionId = null;

            if ($lockedOrder->status === OrderStatus::Rented) {
                [$oldStatus, $oldNote, $oldInspectionId] = $this->releaseOldUnit($lockedItem, $old, $actor, $data['old_vehicle'] ?? []);
                $this->inspections->record($lockedItem, $new, InspectionType::Pickup, $data['pickup'] ?? [], $actor, 'pickup');
            }

            $change = $lockedItem->vehicleChanges()->create([
                'from_vehicle_id' => $old->getKey(),
                'to_vehicle_id' => $new->getKey(),
                'reason' => $reason,
                'from_order_status' => $lockedOrder->status,
                'note' => $data['note'] ?? null,
                'old_unit_price' => $lockedItem->unit_price,
                'new_unit_price' => $new->price_per_day,
                'suggested_amount' => $this->replacements->suggestedAmount($lockedItem, $new),
                'final_amount' => $finalAmount,
                'old_vehicle_status' => $oldStatus,
                'old_vehicle_note' => $oldNote,
                'old_vehicle_inspection_id' => $oldInspectionId,
                'old_vehicle_status_by' => $oldStatus ? $actor->getKey() : null,
                'old_vehicle_status_at' => $oldStatus ? now() : null,
                'changed_by' => $actor->getKey(),
            ]);

            $lockedItem->update(['vehicle_id' => $new->getKey()]);
            $lockedOrder->update(['total_price' => $lockedOrder->total_price + $finalAmount]);

            return $change->load(['fromVehicle', 'toVehicle', 'orderItem.order']);
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureReplacementIsValid(OrderItem $item, Vehicle $old, ?Vehicle $new, VehicleChangeReason $reason): void
    {
        if (! $new || $new->is($old)) {
            throw ValidationException::withMessages(['vehicle_id' => 'Pilih kendaraan pengganti yang berbeda dari unit saat ini.']);
        }

        if (! $new->is_available) {
            throw ValidationException::withMessages(['vehicle_id' => 'Kendaraan pengganti tidak tampil di katalog.']);
        }

        if ($reason->requiresSameCategory() && $new->type !== $old->type) {
            throw ValidationException::withMessages(['vehicle_id' => "Alasan {$reason->label()} hanya mengizinkan kendaraan dengan kategori yang sama ({$old->type->label()})."]);
        }

        [$start, $end] = $this->replacements->window($item);

        if (! Vehicle::query()->availableBetween($start, $end, $item->getKey())->whereKey($new->getKey())->exists()) {
            throw ValidationException::withMessages(['vehicle_id' => 'Kendaraan pengganti tidak tersedia pada sisa rentang sewa pesanan ini.']);
        }
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    private function ensureAmountIsAllowed(Order $order, User $actor, int $finalAmount): void
    {
        if ($finalAmount <= -config('rental.vehicle_change_admin_threshold') && ! $actor->isAdmin()) {
            throw new AuthorizationException('Pengurangan biaya sebesar Rp'.number_format(config('rental.vehicle_change_admin_threshold'), 0, ',', '.').' atau lebih hanya dapat dilakukan admin.');
        }

        if ($order->total_price + $finalAmount < 0) {
            throw ValidationException::withMessages(['final_amount' => 'Total pesanan tidak boleh kurang dari nol.']);
        }
    }

    /**
     * Menetapkan nasib unit lama saat pesanan Disewa.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: OldVehicleStatus, 1: ?string, 2: ?int}
     *
     * @throws ValidationException
     */
    private function releaseOldUnit(OrderItem $item, Vehicle $old, User $actor, array $input): array
    {
        $status = OldVehicleStatus::tryFrom((string) ($input['status'] ?? ''));

        if (! $status) {
            throw ValidationException::withMessages(['old_vehicle.status' => 'Tentukan nasib unit lama: sudah diinspeksi, ditunda, atau hilang/dicuri.']);
        }

        $note = filled($input['note'] ?? null) ? (string) $input['note'] : null;

        if ($status !== OldVehicleStatus::Returned && $note === null) {
            throw ValidationException::withMessages(['old_vehicle.note' => 'Catatan wajib diisi untuk unit lama yang ditunda atau hilang/dicuri.']);
        }

        if ($status === OldVehicleStatus::Lost) {
            $old->update(['is_available' => false]);
        }

        $inspectionId = $status === OldVehicleStatus::Returned
            ? $this->inspections->record($item, $old, InspectionType::Return, $input['inspection'] ?? [], $actor, 'old_vehicle.inspection')->getKey()
            : null;

        return [$status, $status === OldVehicleStatus::Returned ? null : $note, $inspectionId];
    }
}
