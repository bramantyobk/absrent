<?php

namespace App\Models;

use App\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['code', 'user_id', 'assigned_operator_id', 'total_price', 'status', 'expires_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Filter daftar pesanan di dashboard: cari (kode, pelanggan, merek/model/plat kendaraan), status, kategori kendaraan,
     * dan rentang tanggal mulai sewa (`from` dan `to`, inklusif).
     *
     * @param  Builder<Order>  $query
     * @param  array{search?: ?string, status?: ?string, type?: ?string, from?: ?string, to?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('items.vehicle', fn (Builder $vehicle) => $vehicle
                        ->where('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%"))
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->whereHas('items.vehicle', fn (Builder $vehicle) => $vehicle->where('type', $type)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereHas('items', fn (Builder $items) => $items->where('start_at', '>=', Carbon::parse($from)->startOfDay())))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereHas('items', fn (Builder $items) => $items->where('start_at', '<=', Carbon::parse($to)->endOfDay())));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_operator_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<Transaction, $this>
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    /**
     * @return HasMany<CancellationRequest, $this>
     */
    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class);
    }

    /**
     * Kode pesanan unik untuk pesan WhatsApp, mis. ABS-7K3Q9X2M.
     */
    public static function generateCode(): string
    {
        return 'ABS-'.Str::upper(Str::random(8));
    }

    public function formattedTotalPrice(): string
    {
        return 'Rp'.number_format($this->total_price, 0, ',', '.');
    }
}
