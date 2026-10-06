<?php

namespace App;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Booking = 'booking';
    case Rented = 'rented';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Booking => 'Booking',
            self::Rented => 'Disewa',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::Failed => 'Gagal',
        };
    }
}
