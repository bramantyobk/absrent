<?php

namespace App\Models;

use App\OldVehicleStatus;
use App\OrderStatus;
use App\Services\RentalQuote;
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
use Illuminate\Support\Str;

#[Fillable(['brand', 'model', 'plate_number', 'type', 'year', 'color', 'price_per_day', 'price_per_hour', 'photo', 'is_available', 'drive_type', 'energy_capacity', 'description', 'odometer_km', 'max_rental_days'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if (blank($vehicle->slug) || $vehicle->isDirty(['brand', 'model'])) {
                $vehicle->slug = $vehicle->resolveSlug();
            }
        });
    }

    /**
     * Slug dari merek + model (mis. `toyota-avanza`), dengan akhiran angka bila sudah dipakai unit lain. Slug ikut berubah saat
     * merek atau model diubah, tetapi slug yang masih sesuai (termasuk akhiran angkanya) dipertahankan.
     */
    private function resolveSlug(): string
    {
        $base = Str::slug("{$this->brand} {$this->model}") ?: 'kendaraan';
        $current = (string) $this->getOriginal('slug');

        if ($current === $base || preg_match('/^'.preg_quote($base, '/').'-\d+$/', $current)) {
            return $current;
        }

        $slug = $base;

        for ($suffix = 2; static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'drive_type' => VehicleDriveType::class,
            'energy_capacity' => 'decimal:1',
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
            ->whereDoesntHave('outgoingChanges', fn (Builder $changes) => $changes
                ->where('old_vehicle_status', OldVehicleStatus::Pending)
                ->whereHas('orderItem', fn (Builder $items) => $items->where('start_at', '<', $end)))
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
            'orderItems as is_overdue_now' => fn (Builder $items) => $items
                ->where('end_at', '<', $now->copy()->subHours(config('rental.return_buffer_hours')))
                ->whereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Rented)),
            'outgoingChanges as has_unreturned_unit' => fn (Builder $changes) => $changes->where('old_vehicle_status', OldVehicleStatus::Pending),
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
        $isOverdue = $this->flagOrQuery('is_overdue_now', fn () => $this->orderItems()
            ->where('end_at', '<', $now->copy()->subHours(config('rental.return_buffer_hours')))
            ->whereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Rented))
            ->exists());
        $hasUnreturnedUnit = $this->flagOrQuery('has_unreturned_unit', fn () => $this->outgoingChanges()
            ->where('old_vehicle_status', OldVehicleStatus::Pending)
            ->exists());
        $isServicing = $this->flagOrQuery('is_servicing_now', fn () => $this->schedules()
            ->overlapping($now, $now->copy()->addSecond())
            ->exists());
        $isBooked = $this->flagOrQuery('is_booked_now', fn () => $this->orderItems()
            ->holdingBetween($now, $now->copy()->addSecond())
            ->whereHas('order', fn (Builder $order) => $order->whereIn('status', [OrderStatus::Pending, OrderStatus::Booking]))
            ->exists());

        return match (true) {
            $isOverdue, $hasUnreturnedUnit => VehicleStatus::Overdue,
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
     * Penggantian kendaraan yang meninggalkan unit ini dan belum diinspeksi (Ditunda): unit belum kembali, sehingga menahan
     * jadwal mulai dari awal sewa pesanan itu (aturan yang sama dengan `availableBetween`).
     *
     * @return Collection<int, OrderVehicleChange>
     */
    public function conflictingUnreturnedChanges(CarbonInterface $end): Collection
    {
        return $this->outgoingChanges()
            ->where('old_vehicle_status', OldVehicleStatus::Pending)
            ->whereHas('orderItem', fn (Builder $items) => $items->where('start_at', '<', $end))
            ->with('orderItem.order')
            ->orderBy('id')
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
     * Memuat perlengkapan aktif sesuai kategori unit ke relasi `equipment` (berurutan), untuk halaman dan API pelanggan.
     */
    public function loadEquipment(): static
    {
        return $this->setRelation('equipment', EquipmentItem::query()->active()->forCategory($this->type)->ordered()->get());
    }

    /**
     * Batas hari sewa maksimal untuk unit ini: pengaturan unit bila ada, selain itu nilai bawaan.
     */
    public function effectiveMaxRentalDays(): int
    {
        return $this->max_rental_days ?? config('rental.max_rental_days');
    }

    /**
     * Unit yang tampil di katalog pelanggan.
     *
     * @param  Builder<Vehicle>  $query
     */
    #[Scope]
    protected function listedInCatalog(Builder $query): void
    {
        $query->where('is_available', true);
    }

    /**
     * Filter katalog pelanggan: cari, kategori, tipe, dan (bila waktu mulai dan durasi diisi) hanya unit yang kosong pada periode itu, termasuk jeda pengembalian.
     *
     * @param  Builder<Vehicle>  $query
     * @param  array{search?: ?string, type?: ?string, drive_type?: ?string, start_at?: ?string, days?: int|string|null}  $filters
     */
    #[Scope]
    protected function catalogFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('brand', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%")
            ))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['drive_type'] ?? null, fn (Builder $query, string $driveType) => $query->where('drive_type', $driveType))
            ->when(
                ($filters['start_at'] ?? null) && ($filters['days'] ?? null),
                function (Builder $query) use ($filters): void {
                    $start = RentalQuote::parse($filters['start_at']);

                    $query->availableBetween($start, $start->copy()->addDays((int) $filters['days'])->addHours(config('rental.return_buffer_hours')));
                }
            );
    }

    /**
     * Kapasitas energi beserta satuannya (liter atau kWh), atau null bila belum diisi.
     */
    public function energyCapacityLabel(): ?string
    {
        if ($this->energy_capacity === null || $this->drive_type === null) {
            return null;
        }

        $value = rtrim(rtrim(number_format((float) $this->energy_capacity, 1, ',', '.'), '0'), ',');

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
     * Penggantian kendaraan yang meninggalkan unit ini (unit lama).
     *
     * @return HasMany<OrderVehicleChange, $this>
     */
    public function outgoingChanges(): HasMany
    {
        return $this->hasMany(OrderVehicleChange::class, 'from_vehicle_id');
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
