<?php

namespace App\Models;

use App\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active', 'is_on_duty', 'last_assigned_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'is_on_duty' => 'boolean',
            'last_assigned_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Operator], true);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_operator_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function verifiedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'verified_by');
    }

    /**
     * @return HasMany<CancellationRequest, $this>
     */
    public function handledCancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class, 'handled_by');
    }

    /**
     * Apakah user punya riwayat (pesanan, verifikasi, dll) sehingga tidak aman dihapus.
     */
    public function hasHistory(): bool
    {
        return $this->orders()->exists()
            || $this->assignedOrders()->exists()
            || $this->verifiedTransactions()->exists()
            || $this->handledCancellationRequests()->exists();
    }

    /**
     * Normalisasi nomor WhatsApp ke format 62xxxxxxxxxx (08xx menjadi 628xx).
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
