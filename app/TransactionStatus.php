<?php

namespace App;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Verifying = 'verifying';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verifying => 'Menunggu Verifikasi',
            self::Paid => 'Lunas',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Class CSS badge status (lihat resources/sass/_dashboard.scss).
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'status-pending',
            self::Verifying => 'status-progress',
            self::Paid => 'status-lunas',
            self::Failed => 'status-batal',
            self::Cancelled => 'status-batal',
        };
    }
}
