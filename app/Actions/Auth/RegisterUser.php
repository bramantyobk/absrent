<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\UserRole;

class RegisterUser
{
    /**
     * Role pengguna baru selalu `user`, apa pun isi $data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): User
    {
        return User::create([...$data, 'role' => UserRole::User]);
    }
}
