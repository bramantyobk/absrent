<?php

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\ProfilePhotoStorage;

class RemoveProfilePhoto
{
    public function __construct(private ProfilePhotoStorage $photos) {}

    public function handle(User $user): User
    {
        $previous = $user->photo_path;

        $user->update(['photo_path' => null]);

        $this->photos->delete($previous);

        return $user;
    }
}
