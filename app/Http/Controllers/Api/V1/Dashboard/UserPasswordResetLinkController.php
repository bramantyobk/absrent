<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Auth\IssuePasswordResetLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UserPasswordResetLinkController extends Controller
{
    /**
     * Buat tautan reset password
     *
     * Khusus admin dan hanya untuk pelanggan (staf 403). Membuat token baru (token sebelumnya tidak berlaku), menghapus penanda
     * pengajuan, dan mengembalikan `url`, `whatsapp_url` (tautan wa.me ke pelanggan, null bila tanpa nomor), serta masa berlaku.
     */
    public function store(User $user, IssuePasswordResetLink $issuePasswordResetLink): JsonResponse
    {
        Gate::authorize('resetPassword', $user);

        return response()->json(['data' => $issuePasswordResetLink->handle($user)]);
    }
}
