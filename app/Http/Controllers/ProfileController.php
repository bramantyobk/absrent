<?php

namespace App\Http\Controllers;

use App\Actions\Profile\UpdateProfile;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'profileErrors' => $request->session()->get('errors')?->getBag('profile'),
            'photoErrors' => $request->session()->get('errors')?->getBag('photo'),
            'passwordErrors' => $request->session()->get('errors')?->getBag('password'),
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($request->user(), $request->validated());

        return redirect()->route('profile.edit')->with('status', 'Profil berhasil diperbarui.');
    }
}
