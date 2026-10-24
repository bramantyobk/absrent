<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\WhatsAppLink;
use Illuminate\Support\Facades\Password;

class IssuePasswordResetLink
{
    public function __construct(private WhatsAppLink $whatsApp) {}

    /**
     * Membuat token reset (menggantikan token sebelumnya), menghapus penanda pengajuan, dan menyusun tautan WhatsApp ke
     * pelanggan. Token berlaku sesuai `auth.passwords.users.expire` (menit) dan hanya sekali.
     *
     * @return array{url: string, whatsapp_url: ?string, expires_in_minutes: int}
     */
    public function handle(User $user): array
    {
        $minutes = (int) config('auth.passwords.users.expire');
        $url = route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]);

        $user->update(['password_reset_requested_at' => null]);

        $message = "Halo {$user->name}, ini tautan untuk mengatur ulang password akun ABSRENT Anda (berlaku {$minutes} menit dan hanya sekali pakai): {$url}";

        return [
            'url' => $url,
            'whatsapp_url' => blank($user->phone) ? null : $this->whatsApp->make($user->phone, $message),
            'expires_in_minutes' => $minutes,
        ];
    }
}
