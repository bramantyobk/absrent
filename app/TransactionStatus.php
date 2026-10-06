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
}
