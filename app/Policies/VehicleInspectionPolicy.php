<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleInspection;

class VehicleInspectionPolicy
{
    /**
     * Inspeksi yang sudah tersimpan hanya dapat disunting admin. Operator mengoreksi di langkah ringkasan sebelum menyimpan.
     */
    public function update(User $actor, VehicleInspection $inspection): bool
    {
        return $actor->isAdmin();
    }
}
