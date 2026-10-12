<?php

namespace App;

enum VehicleScheduleType: string
{
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Servis',
            self::Other => 'Lainnya',
        };
    }
}
