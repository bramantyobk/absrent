<?php

namespace App\Http\Controllers;

use App\Actions\Profile\RemoveProfilePhoto;
use App\Actions\Profile\UpdateProfilePhoto;
use App\Http\Requests\UpdateProfilePhotoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfilePhotoController extends Controller
{
    public function store(UpdateProfilePhotoRequest $request, UpdateProfilePhoto $updateProfilePhoto): RedirectResponse
    {
        $updateProfilePhoto->handle($request->user(), $request->file('photo'));

        return redirect()->route('profile.edit')->with('status', 'Foto profil berhasil diperbarui.');
    }

    public function destroy(Request $request, RemoveProfilePhoto $removeProfilePhoto): RedirectResponse
    {
        $removeProfilePhoto->handle($request->user());

        return redirect()->route('profile.edit')->with('status', 'Foto profil berhasil dihapus.');
    }
}
