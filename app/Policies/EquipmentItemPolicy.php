<?php

namespace App\Policies;

use App\Models\EquipmentItem;
use App\Models\User;

/**
 * Perlengkapan dikelola staf. Dapat dinonaktifkan (ubah) atau dihapus permanen (aman bagi riwayat karena inspeksi memakai snapshot).
 */
class EquipmentItemPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, EquipmentItem $equipmentItem): bool
    {
        return $actor->isStaff();
    }

    public function create(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function update(User $actor, EquipmentItem $equipmentItem): bool
    {
        return $actor->isStaff();
    }

    public function delete(User $actor, EquipmentItem $equipmentItem): bool
    {
        return $actor->isStaff();
    }
}
