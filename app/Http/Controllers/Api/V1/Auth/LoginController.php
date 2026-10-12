<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginApiUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Masuk dan dapatkan token
     *
     * Mengembalikan token Bearer per perangkat (`device_name`). Akun nonaktif atau kredensial salah menghasilkan 422.
     */
    public function store(LoginRequest $request, LoginApiUser $loginApiUser): JsonResponse
    {
        $user = $loginApiUser->handle($request->validated('email'), $request->validated('password'));

        return response()->json([
            'data' => [
                'token' => $user->createToken($request->validated('device_name'))->plainTextToken,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }
}
