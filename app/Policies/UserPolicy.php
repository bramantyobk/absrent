<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Operator hanya boleh melihat daftar pengguna (read-only).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Admin tidak dapat menghapus akunnya sendiri.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin() && ! $actor->is($target);
    }
}
