<?php

namespace App\Policies;

use App\Models\CompanyProfile;
use App\Models\User;

/**
 * Data perusahaan hanya satu baris dan khusus admin. Tidak ada ability tambah dan hapus.
 */
class CompanyProfilePolicy
{
    public function view(User $actor, CompanyProfile $companyProfile): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, CompanyProfile $companyProfile): bool
    {
        return $actor->isAdmin();
    }
}
