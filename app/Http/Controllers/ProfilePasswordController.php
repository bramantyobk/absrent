<?php

namespace App\Http\Controllers;

use App\Actions\Profile\ChangePassword;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;

class ProfilePasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, ChangePassword $changePassword): RedirectResponse
    {
        $changePassword->handle($request->user(), $request->validated('password'));

        return redirect()->route('profile.edit')->with('status', 'Password berhasil diganti.');
    }
}
