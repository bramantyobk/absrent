<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, Vehicle $vehicle): bool
    {
        return $actor->isStaff();
    }

    public function create(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function update(User $actor, Vehicle $vehicle): bool
    {
        return $actor->isStaff();
    }

    public function delete(User $actor, Vehicle $vehicle): bool
    {
        return $actor->isStaff();
    }
}
