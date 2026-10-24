<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetUserPassword
{
    /**
     * Mengatur password baru dengan token dari admin. Token hanya berlaku sekali dan kedaluwarsa menurut konfigurasi.
     * Sesi "ingat saya" dan token API lama dicabut agar perangkat lain harus masuk ulang.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     * @return bool false bila token salah atau sudah kedaluwarsa
     */
    public function handle(array $credentials): bool
    {
        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'password_reset_requested_at' => null,
            ])->save();

            $user->tokens()->delete();

            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET;
    }
}
