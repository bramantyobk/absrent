<?php

namespace App\Policies;

use App\Models\CancellationRequest;
use App\Models\User;

class CancellationRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function handle(User $actor, CancellationRequest $cancellationRequest): bool
    {
        return $actor->isStaff();
    }
}
