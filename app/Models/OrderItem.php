<?php

namespace App\Models;

use App\OrderStatus;
use Carbon\CarbonInterface;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'vehicle_id', 'start_at', 'end_at', 'days', 'unit_price', 'subtotal'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Item pesanan aktif (Pending/Booking/Disewa) yang menahan unit pada rentang [start, end).
     * Pesanan menahan unit sampai end_at ditambah jeda pengembalian, dan pesanan Disewa yang
     * terlambat (sudah lewat end_at + jeda) tetap menahan sampai operator mengubahnya menjadi Selesai;
     * pesanan Disewa yang belum terlambat mengikuti aturan biasa.
     *
     * @param  Builder<OrderItem>  $query
     */
    #[Scope]
    protected function holdingBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $buffer = config('rental.return_buffer_hours');
        $releasedAfter = $start->copy()->subHours($buffer);

        $query
            ->where('start_at', '<', $end)
            ->whereHas('order', fn (Builder $order) => $order->whereIn('status', OrderStatus::holdingSchedule()))
            ->where(fn (Builder $query) => $query
                ->where('end_at', '>', $releasedAfter)
                ->orWhere(fn (Builder $overdue) => $overdue
                    ->where('end_at', '<=', now()->subHours($buffer))
                    ->whereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Rented))));
    }

    /**
     * @return HasMany<VehicleInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(VehicleInspection::class);
    }

    /**
     * Riwayat ganti kendaraan pada item ini.
     *
     * @return HasMany<OrderVehicleChange, $this>
     */
    public function vehicleChanges(): HasMany
    {
        return $this->hasMany(OrderVehicleChange::class);
    }
}
