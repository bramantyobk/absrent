<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profile\ChangePassword;
use App\Actions\Profile\RemoveProfilePhoto;
use App\Actions\Profile\UpdateProfile;
use App\Actions\Profile\UpdateProfilePhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfilePhotoRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileController extends Controller
{
    /**
     * Ubah profil
     *
     * Nama dan nomor WhatsApp (dinormalkan ke `62...`). Email tidak dapat diubah. Mengubah nomor menghapus penanda nomor ganda.
     * Hanya untuk pelanggan.
     */
    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): UserResource
    {
        return new UserResource($updateProfile->handle($request->user(), $request->validated()));
    }

    /**
     * Ganti foto profil
     *
     * Multipart dengan field `photo` (JPG, PNG, atau WebP, maksimal 1 MB). Foto lama dihapus. Hanya untuk pelanggan.
     */
    public function updatePhoto(UpdateProfilePhotoRequest $request, UpdateProfilePhoto $updateProfilePhoto): UserResource
    {
        return new UserResource($updateProfilePhoto->handle($request->user(), $request->file('photo')));
    }

    /**
     * Hapus foto profil
     *
     * Kembali ke tampilan inisial. Hanya untuk pelanggan.
     */
    public function destroyPhoto(Request $request, RemoveProfilePhoto $removeProfilePhoto): UserResource
    {
        return new UserResource($removeProfilePhoto->handle($request->user()));
    }

    /**
     * Ganti password
     *
     * Wajib mengirim `current_password` (password lama), `password`, dan `password_confirmation`. Token API lain milik pengguna
     * dicabut, token yang sedang dipakai tetap berlaku. Berbeda dari lupa password. Hanya untuk pelanggan.
     */
    public function updatePassword(ChangePasswordRequest $request, ChangePassword $changePassword): Response
    {
        $changePassword->handle($request->user(), $request->validated('password'));

        /** @status 204 */
        return response()->noContent();
    }
}
