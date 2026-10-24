<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResetUserPassword;
use App\Http\Controllers\Auth\ResetPasswordController as WebResetPasswordController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    /**
     * Atur ulang password
     *
     * Memakai `token` dan `email` dari tautan reset yang dikirim admin, ditambah `password` dan `password_confirmation`. Token
     * berlaku 10 menit dan sekali pakai (422 bila salah atau kedaluwarsa). Token API lama pengguna dicabut.
     */
    public function store(ResetPasswordRequest $request, ResetUserPassword $resetUserPassword): JsonResponse
    {
        if (! $resetUserPassword->handle($request->validated() + ['password_confirmation' => $request->input('password_confirmation')])) {
            throw ValidationException::withMessages(['email' => WebResetPasswordController::INVALID_MESSAGE]);
        }

        return response()->json(['message' => 'Password berhasil diatur ulang.']);
    }
}
