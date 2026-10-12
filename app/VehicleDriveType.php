<?php

namespace App;

enum VehicleDriveType: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Electric = 'electric';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Matic',
            self::Manual => 'Manual',
            self::Electric => 'Listrik',
        };
    }

    /**
     * Satuan kapasitas energi: kWh untuk listrik, liter untuk lainnya.
     */
    public function energyUnit(): string
    {
        return $this === self::Electric ? 'kWh' : 'liter';
    }
}
