<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserPhoneReviewController extends Controller
{
    /**
     * Menandai nomor WhatsApp pelanggan sudah ditinjau (menghapus penanda "Nomor perlu ditinjau").
     */
    public function __invoke(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->update(['phone_flagged_at' => null]);

        return back()->with('status', "Nomor WhatsApp {$user->name} ditandai sudah ditinjau.");
    }
}
