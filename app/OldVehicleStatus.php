<?php

namespace App;

/**
 * Nasib unit lama pada ganti kendaraan dari pesanan `Disewa`.
 */
enum OldVehicleStatus: string
{
    case Returned = 'returned';
    case Pending = 'pending';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Returned => 'Sudah diinspeksi',
            self::Pending => 'Ditunda',
            self::Lost => 'Hilang/dicuri',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Returned => 'status-lunas',
            self::Pending => 'status-progress',
            self::Lost => 'status-batal',
        };
    }
}
