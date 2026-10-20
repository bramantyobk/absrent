<?php

namespace App\Policies;

use App\Models\RentalSetting;
use App\Models\User;

/**
 * Pengaturan sewa hanya satu baris dan khusus admin. Tidak ada ability tambah dan hapus.
 */
class RentalSettingPolicy
{
    public function view(User $actor, RentalSetting $rentalSetting): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, RentalSetting $rentalSetting): bool
    {
        return $actor->isAdmin();
    }
}
