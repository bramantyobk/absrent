<?php

namespace App\Models;

use App\VehicleScheduleType;
use Carbon\CarbonInterface;
use Database\Factories\VehicleScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_id', 'type', 'start_at', 'end_at', 'note', 'odometer_km', 'created_by'])]
class VehicleSchedule extends Model
{
    /** @use HasFactory<VehicleScheduleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleScheduleType::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * Interval setengah terbuka [start_at, end_at). Setelah jadwal tidak ada jeda.
     *
     * @param  Builder<VehicleSchedule>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }

    public function hasStarted(): bool
    {
        return $this->start_at->lte(now());
    }

    public function hasEnded(): bool
    {
        return $this->end_at->lte(now());
    }

    public function isRunning(): bool
    {
        return $this->hasStarted() && ! $this->hasEnded();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
