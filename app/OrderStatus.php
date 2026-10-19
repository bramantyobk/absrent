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
    case Stolen = 'stolen';

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
            self::Stolen => 'Dicuri',
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
            self::Stolen => 'status-batal',
        };
    }

    /**
     * Status pesanan yang menahan jadwal kendaraan.
     *
     * @return array<int, self>
     */
    public static function holdingSchedule(): array
    {
        return [self::Pending, self::Booking, self::Rented];
    }

    /**
     * Status pesanan yang pendapatannya dihitung (bersama transaksi Lunas): pembayaran sudah diterima dan tidak dikembalikan.
     * Dicuri tetap dihitung karena ganti rugi diurus di luar sistem, sedangkan Refund, Dibatalkan, dan Gagal tidak.
     *
     * @return array<int, self>
     */
    public static function revenueStatuses(): array
    {
        return [self::Booking, self::Rented, self::Completed, self::Stolen];
    }
}
