<?php

namespace App\Services;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pencarian pelanggan (role `user`) untuk pesanan manual: nomor WhatsApp lebih dulu (dinormalkan ke 62...), lalu email atau nama.
 */
class CustomerLookup
{
    public const LIMIT = 10;

    /**
     * Nomor yang cocok persis tampil paling atas. Tanpa nomor dan kata kunci, hasilnya kosong.
     *
     * @return Collection<int, User>
     */
    public function search(?string $phone, ?string $query): Collection
    {
        $normalizedPhone = filled($phone) ? User::normalizePhone($phone) : null;
        $term = filled($query) ? trim((string) $query) : null;

        if ($normalizedPhone === null && $term === null) {
            return collect();
        }

        return User::query()
            ->where('role', UserRole::User)
            ->where(fn (Builder $matches) => $matches
                ->when($normalizedPhone, fn (Builder $matches, string $number) => $matches->orWhere('phone', $number))
                ->when($term, fn (Builder $matches, string $term) => $matches->orWhere('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($normalizedPhone, fn (Builder $users, string $number) => $users->orderByRaw('case when phone = ? then 0 else 1 end', [$number]))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
