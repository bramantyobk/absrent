<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public const INVALID_MESSAGE = 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru ke admin.';

    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function store(ResetPasswordRequest $request, ResetUserPassword $resetUserPassword): RedirectResponse
    {
        if (! $resetUserPassword->handle($request->validated() + ['password_confirmation' => $request->input('password_confirmation')])) {
            return back()->withInput($request->only('email'))->withErrors(['email' => self::INVALID_MESSAGE]);
        }

        return redirect()->route('login')->with('status', 'Password berhasil diatur ulang. Silakan masuk.');
    }
}
