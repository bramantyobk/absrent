<?php

namespace App\Models;

use App\Services\ProfilePhotoStorage;
use App\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'photo_path', 'password', 'role', 'is_active', 'is_on_duty', 'last_assigned_at', 'phone_flagged_at', 'password_reset_requested_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'phone_flagged_at' => 'datetime',
            'password_reset_requested_at' => 'datetime',
        ];
    }

    /**
     * URL foto profil, atau null bila belum ada (tampilan memakai inisial).
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_path
            ? Storage::disk(ProfilePhotoStorage::DISK)->url($this->photo_path)
            : null);
    }

    /**
     * Inisial dari maksimal dua kata pertama nama, untuk avatar tanpa foto.
     */
    public function initials(): string
    {
        return mb_strtoupper(collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)->map(fn (string $word): string => mb_substr($word, 0, 1))->implode(''));
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
     * Pesanan yang dibuat user ini sebagai staf (pesanan manual).
     *
     * @return HasMany<Order, $this>
     */
    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
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
            || $this->createdOrders()->exists()
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
