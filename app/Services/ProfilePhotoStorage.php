<?php

namespace App\Services;

/**
 * Foto profil pelanggan di disk publik: logika sama dengan foto kendaraan, tetapi direktori sendiri dan lebar maksimal lebih kecil.
 */
class ProfilePhotoStorage extends VehiclePhotoStorage
{
    protected const DIRECTORY = 'profiles';

    protected const MAX_WIDTH = 600;
}
