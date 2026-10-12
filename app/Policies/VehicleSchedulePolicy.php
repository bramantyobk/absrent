<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleSchedule;

class VehicleSchedulePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function create(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function update(User $actor, VehicleSchedule $schedule): bool
    {
        return $actor->isStaff();
    }

    public function delete(User $actor, VehicleSchedule $schedule): bool
    {
        return $actor->isStaff();
    }
}
