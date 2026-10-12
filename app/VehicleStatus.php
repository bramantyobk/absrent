<?php

namespace App;

/**
 * Status turunan kendaraan pada suatu saat. Dihitung dari jadwal, tidak disimpan.
 */
enum VehicleStatus: string
{
    case Available = 'available';
    case Booked = 'booked';
    case Rented = 'rented';
    case Servicing = 'servicing';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Booked => 'Dipesan',
            self::Rented => 'Disewa',
            self::Servicing => 'Servis',
            self::Hidden => 'Tidak tampil',
        };
    }

    /**
     * Class CSS badge status (lihat resources/sass/components/_status-badge.scss).
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'status-lunas',
            self::Booked => 'status-progress',
            self::Rented => 'status-pending',
            self::Servicing => 'status-progress',
            self::Hidden => 'status-batal',
        };
    }
}
