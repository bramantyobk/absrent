<?php

namespace App\Actions\Profile;

use App\Models\User;

class ChangePassword
{
    /**
     * Password lama sudah diperiksa Form Request. Token API lain milik pengguna dicabut (kecuali token yang sedang dipakai),
     * agar perangkat lain harus masuk ulang dengan password baru.
     */
    public function handle(User $user, string $password): User
    {
        $user->update(['password' => $password]);

        $currentToken = $user->currentAccessToken();

        $user->tokens()
            ->when($currentToken?->getKey(), fn ($tokens, $id) => $tokens->whereKeyNot($id))
            ->delete();

        return $user;
    }
}
