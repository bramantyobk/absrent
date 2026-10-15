<?php

namespace App;

enum VehicleChangeReason: string
{
    case CustomerRequest = 'customer_request';
    case Accident = 'accident';
    case VehicleDamaged = 'vehicle_damaged';
    case VehicleLateReturn = 'vehicle_late_return';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerRequest => 'Permintaan Pelanggan',
            self::Accident => 'Kecelakaan',
            self::VehicleDamaged => 'Rusak',
            self::VehicleLateReturn => 'Unit Terlambat',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Hanya Permintaan Pelanggan yang boleh bertukar lintas kategori (motor ke mobil dan sebaliknya).
     */
    public function requiresSameCategory(): bool
    {
        return $this !== self::CustomerRequest;
    }
}
