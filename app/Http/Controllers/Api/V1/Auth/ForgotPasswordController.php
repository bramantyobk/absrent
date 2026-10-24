<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RequestPasswordReset;
use App\Http\Controllers\Auth\ForgotPasswordController as WebForgotPasswordController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    /**
     * Ajukan lupa password
     *
     * Mencatat pengajuan untuk pelanggan dengan email itu, lalu admin mengirim tautan reset lewat WhatsApp. Jawabannya selalu
     * sama (200) agar tidak membocorkan apakah email terdaftar. Email staf diabaikan.
     */
    public function store(ForgotPasswordRequest $request, RequestPasswordReset $requestPasswordReset): JsonResponse
    {
        $requestPasswordReset->handle($request->validated('email'));

        return response()->json(['message' => WebForgotPasswordController::RECEIVED_MESSAGE]);
    }
}
