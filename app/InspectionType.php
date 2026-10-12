<?php

namespace App;

enum InspectionType: string
{
    case Pickup = 'pickup';
    case Return = 'return';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Pengambilan',
            self::Return => 'Pengembalian',
            self::Manual => 'Manual',
        };
    }
}
