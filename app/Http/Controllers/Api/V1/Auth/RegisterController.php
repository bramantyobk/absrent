<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * Daftar sebagai pelanggan
     *
     * Role akun baru selalu `user`. Respons memuat token Bearer untuk perangkat yang disebut di `device_name`.
     */
    public function store(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle($request->safe()->except('device_name'));

        return response()->json([
            'data' => [
                'token' => $user->createToken($request->validated('device_name'))->plainTextToken,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ], 201);
    }
}
