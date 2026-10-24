<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RequestPasswordReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public const RECEIVED_MESSAGE = 'Permintaan diterima. Admin akan mengirim tautan reset ke WhatsApp Anda.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request, RequestPasswordReset $requestPasswordReset): RedirectResponse
    {
        $requestPasswordReset->handle($request->validated('email'));

        return back()->with('status', self::RECEIVED_MESSAGE);
    }
}
