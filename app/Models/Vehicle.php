<?php

namespace App\Models;

use App\OrderStatus;
use App\Services\VehiclePhotoStorage;
use App\VehicleDriveType;
use App\VehicleStatus;
use App\VehicleType;
use Carbon\CarbonInterface;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['brand', 'model', 'plate_number', 'type', 'year', 'color', 'price_per_day', 'price_per_hour', 'photo', 'is_available', 'drive_type', 'energy_capacity', 'description', 'odometer_km'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'drive_type' => VehicleDriveType::class,
            'energy_capacity' => 'float',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Vehicle>  $query
     * @param  array{search?: ?string, type?: ?string, status?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")
            ))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_available', $status === 'available'));
    }

    /**
     * Aturan ketersediaan satu-satunya: unit tampil di katalog, tidak ada pesanan aktif yang menahan
     * rentang (termasuk jeda pengembalian), dan tidak ada jadwal servis yang overlap.
     *
     * @param  Builder<Vehicle>  $query
     * @param  int|null  $ignoreOrderItemId  item pesanan yang dikecualikan (mis. saat ganti kendaraan)
     */
    #[Scope]
    protected function availableBetween(Builder $query, CarbonInterface $start, CarbonInterface $end, ?int $ignoreOrderItemId = null): void
    {
        $query
            ->where('is_available', true)
            ->whereDoesntHave('orderItems', fn (Builder $items) => $items
                ->holdingBetween($start, $end)
                ->when($ignoreOrderItemId, fn (Builder $items, int $id) => $items->whereKeyNot($id)))
            ->whereDoesntHave('schedules', fn (Builder $schedules) => $schedules->overlapping($start, $end));
    }

    /**
     * @param  Builder<Vehicle>  $query
     */
    #[Scope]
    protected function availableAt(Builder $query, CarbonInterface $at): void
    {
        $query->availableBetween($at, $at->copy()->addSecond());
    }

    /**
     * Menambahkan penanda yang dibutuhkan displayStatus() agar daftar kendaraan tidak memicu N+1.
     *
     * @param  Builder<Vehicle>  $query
     */
    #[Scope]
    protected function withDisplayStatus(Builder $query): void
    {
        $now = now();
        $untilNextSecond = $now->copy()->addSecond();

        $query->withExists([
            'orderItems as is_rented_now' => fn (Builder $items) => $items
                ->whereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Rented)),
            'orderItems as is_booked_now' => fn (Builder $items) => $items
                ->holdingBetween($now, $untilNextSecond)
                ->whereHas('order', fn (Builder $order) => $order->whereIn('status', [OrderStatus::Pending, OrderStatus::Booking])),
            'schedules as is_servicing_now' => fn (Builder $schedules) => $schedules->overlapping($now, $untilNextSecond),
        ]);
    }

    public function displayStatus(): VehicleStatus
    {
        $now = now();

        $isRented = $this->flagOrQuery('is_rented_now', fn () => $this->orderItems()
            ->whereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Rented))
            ->exists());
        $isServicing = $this->flagOrQuery('is_servicing_now', fn () => $this->schedules()
            ->overlapping($now, $now->copy()->addSecond())
            ->exists());
        $isBooked = $this->flagOrQuery('is_booked_now', fn () => $this->orderItems()
            ->holdingBetween($now, $now->copy()->addSecond())
            ->whereHas('order', fn (Builder $order) => $order->whereIn('status', [OrderStatus::Pending, OrderStatus::Booking]))
            ->exists());

        return match (true) {
            $isRented => VehicleStatus::Rented,
            $isServicing => VehicleStatus::Servicing,
            $isBooked => VehicleStatus::Booked,
            ! $this->is_available => VehicleStatus::Hidden,
            default => VehicleStatus::Available,
        };
    }

    /**
     * Pesanan aktif yang menahan rentang ini (dengan jeda pengembalian), untuk pesan bentrok.
     *
     * @return Collection<int, OrderItem>
     */
    public function conflictingOrderItems(CarbonInterface $start, CarbonInterface $end, ?int $ignoreOrderItemId = null): Collection
    {
        return $this->orderItems()
            ->holdingBetween($start, $end)
            ->when($ignoreOrderItemId, fn (Builder $items, int $id) => $items->whereKeyNot($id))
            ->with('order')
            ->orderBy('start_at')
            ->get();
    }

    /**
     * @return Collection<int, VehicleSchedule>
     */
    public function conflictingSchedules(CarbonInterface $start, CarbonInterface $end, ?int $ignoreScheduleId = null): Collection
    {
        return $this->schedules()
            ->overlapping($start, $end)
            ->when($ignoreScheduleId, fn (Builder $schedules, int $id) => $schedules->whereKeyNot($id))
            ->orderBy('start_at')
            ->get();
    }

    /**
     * Kapasitas energi beserta satuannya (liter atau kWh), atau null bila belum diisi.
     */
    public function energyCapacityLabel(): ?string
    {
        if ($this->energy_capacity === null || $this->drive_type === null) {
            return null;
        }

        $value = rtrim(rtrim(number_format($this->energy_capacity, 1, ',', '.'), '0'), ',');

        return "{$value} {$this->drive_type->energyUnit()}";
    }

    /**
     * URL foto, atau gambar placeholder jika kendaraan belum punya foto.
     *
     * @return Attribute<string, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->photo
            ? Storage::disk(VehiclePhotoStorage::DISK)->url($this->photo)
            : asset('images/vehicle-placeholder.png'));
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<VehicleSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(VehicleSchedule::class);
    }

    /**
     * @return HasMany<VehicleInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(VehicleInspection::class);
    }

    private function flagOrQuery(string $attribute, callable $query): bool
    {
        return array_key_exists($attribute, $this->attributes)
            ? (bool) $this->attributes[$attribute]
            : $query();
    }
}
