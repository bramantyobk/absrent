<?php

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\ProfilePhotoStorage;
use Illuminate\Http\UploadedFile;
use Throwable;

class UpdateProfilePhoto
{
    public function __construct(private ProfilePhotoStorage $photos) {}

    /**
     * Menyimpan foto baru lalu menghapus foto lama. Foto baru dihapus kembali bila penyimpanan data gagal.
     */
    public function handle(User $user, UploadedFile $photo): User
    {
        $previous = $user->photo_path;
        $path = $this->photos->store($photo);

        try {
            $user->update(['photo_path' => $path]);
        } catch (Throwable $exception) {
            $this->photos->delete($path);

            throw $exception;
        }

        $this->photos->delete($previous);

        return $user;
    }
}
