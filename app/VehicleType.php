<?php

namespace App;

enum VehicleType: string
{
    case Motorcycle = 'motorcycle';
    case Car = 'car';

    public function label(): string
    {
        return match ($this) {
            self::Motorcycle => 'Motor',
            self::Car => 'Mobil',
        };
    }
}
