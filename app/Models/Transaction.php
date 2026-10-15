<?php

namespace App\Models;

use App\TransactionStatus;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'bank_account_id', 'amount', 'proof_path', 'confirmed_at', 'status', 'note', 'verified_by', 'verified_at'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'confirmed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  array{search?: ?string, status?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('order', fn (Builder $order) => $order
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * Transaksi Menunggu Verifikasi yang batas verifikasinya (`orders.expires_at`) tinggal sedikit.
     *
     * @param  Builder<Transaction>  $query
     */
    #[Scope]
    protected function nearExpiry(Builder $query): void
    {
        $query
            ->where('status', TransactionStatus::Verifying)
            ->whereHas('order', fn (Builder $order) => $order->where('expires_at', '<=', now()->addHours(config('rental.verification_warning_hours'))));
    }

    /**
     * Apakah batas verifikasi tinggal sedikit (hanya bermakna saat Menunggu Verifikasi dan `order` dimuat).
     */
    public function isNearExpiry(): bool
    {
        return $this->status === TransactionStatus::Verifying
            && $this->order->expires_at?->lte(now()->addHours(config('rental.verification_warning_hours'))) === true;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
