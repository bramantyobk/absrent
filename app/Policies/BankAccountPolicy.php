<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

/**
 * Rekening bank khusus admin. Tidak ada ability hapus: rekening hanya dinonaktifkan agar data transaksi tetap valid.
 */
class BankAccountPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, BankAccount $bankAccount): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, BankAccount $bankAccount): bool
    {
        return $actor->isAdmin();
    }
}
