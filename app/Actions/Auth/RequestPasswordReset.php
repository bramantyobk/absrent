<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\UserRole;

class RequestPasswordReset
{
    /**
     * Mencatat pengajuan lupa password untuk pelanggan aktif dengan email itu. Email lain (tidak dikenal, staf, nonaktif)
     * diabaikan tanpa tanda apa pun agar pemanggil tidak dapat menebak akun yang terdaftar.
     */
    public function handle(string $email): void
    {
        User::query()
            ->where('email', $email)
            ->where('role', UserRole::User)
            ->where('is_active', true)
            ->update(['password_reset_requested_at' => now()]);
    }
}
