<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginApiUser
{
    /**
     * Pesan error sengaja sama untuk email tidak dikenal, password salah, dan akun nonaktif.
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password): User
    {
        $user = User::query()->where('email', $email)->where('is_active', true)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah, atau akun tidak aktif.',
            ]);
        }

        return $user;
    }
}
