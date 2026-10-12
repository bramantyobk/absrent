<?php

namespace App\Policies;

use App\Models\EquipmentItem;
use App\Models\User;

/**
 * Perlengkapan tidak dihapus, hanya dinonaktifkan, jadi tidak ada ability delete.
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
}
