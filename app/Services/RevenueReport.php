<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\OrderVehicleChange;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use App\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan pendapatan. Pendapatan dicatat pada tanggal verifikasi transaksi Lunas (subtotal item), dan selisih biaya ganti kendaraan
 * pada tanggal perubahan (bertanda: tambah bayar menambah, pengurangan mengurangi). Hanya pesanan berstatus
 * `OrderStatus::revenueStatuses()`. Seluruh nilai dikreditkan ke unit yang sedang terpasang pada item (unit pengganti bila pernah
 * diganti), sehingga laporan hari sebelumnya per kendaraan dan per kategori berubah setelah penggantian (total tidak).
 */
class RevenueReport
{
    /**
     * Pendapatan per hari dan per kendaraan, dikelompokkan sekali agar semua ringkasan konsisten.
     *
     * @return Collection<int, array{day: string, vehicle_id: int, base: int, delta: int}>
     */
    private function rows(Carbon $start, Carbon $end, ?VehicleType $type): Collection
    {
        $base = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('transactions', 'transactions.order_id', '=', 'orders.id')
            ->join('vehicles', 'vehicles.id', '=', 'order_items.vehicle_id')
            ->where('transactions.status', TransactionStatus::Paid->value)
            ->whereIn('orders.status', array_column(OrderStatus::revenueStatuses(), 'value'))
            ->whereBetween('transactions.verified_at', [$start, $end])
            ->when($type, fn (Builder $query, VehicleType $type) => $query->where('vehicles.type', $type->value))
            ->groupBy(DB::raw('DATE(transactions.verified_at)'), 'order_items.vehicle_id')
            ->selectRaw('DATE(transactions.verified_at) as day, order_items.vehicle_id as vehicle_id, SUM(order_items.subtotal) as amount')
            ->toBase()
            ->get();

        $delta = OrderVehicleChange::query()
            ->join('order_items', 'order_items.id', '=', 'order_vehicle_changes.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('transactions', 'transactions.order_id', '=', 'orders.id')
            ->join('vehicles', 'vehicles.id', '=', 'order_items.vehicle_id')
            ->where('transactions.status', TransactionStatus::Paid->value)
            ->whereIn('orders.status', array_column(OrderStatus::revenueStatuses(), 'value'))
            ->whereBetween('order_vehicle_changes.created_at', [$start, $end])
            ->when($type, fn (Builder $query, VehicleType $type) => $query->where('vehicles.type', $type->value))
            ->groupBy(DB::raw('DATE(order_vehicle_changes.created_at)'), 'order_items.vehicle_id')
            ->selectRaw('DATE(order_vehicle_changes.created_at) as day, order_items.vehicle_id as vehicle_id, SUM(order_vehicle_changes.final_amount) as amount')
            ->toBase()
            ->get();

        return $base->map(fn (object $row): array => ['day' => $row->day, 'vehicle_id' => (int) $row->vehicle_id, 'base' => (int) $row->amount, 'delta' => 0])
            ->concat($delta->map(fn (object $row): array => ['day' => $row->day, 'vehicle_id' => (int) $row->vehicle_id, 'base' => 0, 'delta' => (int) $row->amount]));
    }

    /**
     * Setiap hari pada rentang (tanpa lubang): subtotal, selisih ganti kendaraan, dan totalnya.
     *
     * @return Collection<int, array{day: string, label: string, base: int, delta: int, total: int}>
     */
    public function daily(Carbon $start, Carbon $end, ?VehicleType $type = null): Collection
    {
        $byDay = $this->rows($start, $end, $type)->groupBy('day');

        return collect($start->copy()->startOfDay()->toPeriod($end))->map(function (Carbon $day) use ($byDay): array {
            $rows = $byDay->get($day->toDateString(), collect());
            $base = (int) $rows->sum('base');
            $delta = (int) $rows->sum('delta');

            return ['day' => $day->toDateString(), 'label' => $day->format('d/m'), 'base' => $base, 'delta' => $delta, 'total' => $base + $delta];
        });
    }

    /**
     * Pendapatan per kendaraan (unit yang sedang terpasang), terbesar lebih dulu.
     *
     * @return Collection<int, array{vehicle_id: int, name: string, plate_number: string, type: string, base: int, delta: int, total: int}>
     */
    public function byVehicle(Carbon $start, Carbon $end, ?VehicleType $type = null): Collection
    {
        $totals = $this->rows($start, $end, $type)->groupBy('vehicle_id');

        return Vehicle::query()->whereIn('id', $totals->keys())->get()
            ->map(function (Vehicle $vehicle) use ($totals): array {
                $rows = $totals[$vehicle->id];
                $base = (int) $rows->sum('base');
                $delta = (int) $rows->sum('delta');

                return [
                    'vehicle_id' => $vehicle->id,
                    'name' => "{$vehicle->brand} {$vehicle->model}",
                    'plate_number' => $vehicle->plate_number,
                    'type' => $vehicle->type->value,
                    'base' => $base,
                    'delta' => $delta,
                    'total' => $base + $delta,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Rincian selisih biaya ganti kendaraan pada rentang, terbaru dahulu.
     *
     * @return Collection<int, OrderVehicleChange>
     */
    public function changes(Carbon $start, Carbon $end, ?VehicleType $type = null): Collection
    {
        return OrderVehicleChange::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('orderItem', fn (Builder $items) => $items
                ->when($type, fn (Builder $items, VehicleType $type) => $items->whereHas('vehicle', fn (Builder $vehicles) => $vehicles->where('type', $type)))
                ->whereHas('order', fn (Builder $order) => $order
                    ->whereIn('status', OrderStatus::revenueStatuses())
                    ->whereHas('transaction', fn (Builder $transaction) => $transaction->where('status', TransactionStatus::Paid))))
            ->with(['orderItem.order', 'fromVehicle', 'toVehicle'])
            ->latest('created_at')
            ->latest('id')
            ->get();
    }
}
