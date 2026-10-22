<?php

namespace App\Actions\Profile;

use App\Models\User;

class UpdateProfile
{
    /**
     * Mengubah nama dan nomor WhatsApp sendiri. Email tidak dapat diubah (identitas login). Penanda nomor ganda (`phone_flagged_at`)
     * dihapus saat nomor berubah, seperti saat admin mengubahnya, karena pemilik akun sendiri yang menetapkan nomor barunya.
     *
     * @param  array{name: string, phone: string}  $data
     */
    public function handle(User $user, array $data): User
    {
        $phoneChanged = $data['phone'] !== $user->phone;

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'phone_flagged_at' => $phoneChanged ? null : $user->phone_flagged_at,
        ]);

        return $user;
    }
}
