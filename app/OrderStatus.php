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
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Booking => 'Booking',
            self::Rented => 'Disewa',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::Failed => 'Gagal',
            self::Refunded => 'Refund',
        };
    }

    /**
     * Class CSS badge status (lihat resources/sass/_dashboard.scss).
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'status-pending',
            self::Booking => 'status-progress',
            self::Rented => 'status-lunas',
            self::Completed => 'status-returned',
            self::Cancelled => 'status-batal',
            self::Failed => 'status-batal',
            self::Refunded => 'status-refund',
        };
    }
}
