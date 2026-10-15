<?php

namespace App\Models;

use App\OldVehicleStatus;
use App\OrderStatus;
use App\VehicleChangeReason;
use Database\Factories\OrderVehicleChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_item_id', 'from_vehicle_id', 'to_vehicle_id', 'reason', 'from_order_status', 'note', 'old_unit_price', 'new_unit_price',
    'suggested_amount', 'final_amount', 'old_vehicle_status', 'old_vehicle_note', 'old_vehicle_inspection_id', 'old_vehicle_status_by',
    'old_vehicle_status_at', 'changed_by',
])]
class OrderVehicleChange extends Model
{
    /** @use HasFactory<OrderVehicleChangeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => VehicleChangeReason::class,
            'from_order_status' => OrderStatus::class,
            'old_vehicle_status' => OldVehicleStatus::class,
            'old_vehicle_status_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function fromVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'from_vehicle_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function toVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'to_vehicle_id');
    }

    /**
     * @return BelongsTo<VehicleInspection, $this>
     */
    public function oldVehicleInspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'old_vehicle_inspection_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function oldVehicleStatusBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_vehicle_status_by');
    }
}
