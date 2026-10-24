<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Auth\IssuePasswordResetLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserPasswordResetLinkController extends Controller
{
    /**
     * Membuat tautan reset lalu membuka WhatsApp ke pelanggan (form memakai tab baru), sehingga admin tinggal menekan kirim.
     */
    public function store(User $user, IssuePasswordResetLink $issuePasswordResetLink): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $link = $issuePasswordResetLink->handle($user);

        if ($link['whatsapp_url'] === null) {
            return back()->with('error', "{$user->name} belum memiliki nomor WhatsApp. Atur passwordnya lewat menu Ubah.");
        }

        return redirect()->away($link['whatsapp_url']);
    }
}
