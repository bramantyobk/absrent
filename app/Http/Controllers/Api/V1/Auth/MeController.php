<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Profil pengguna yang sedang masuk
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
