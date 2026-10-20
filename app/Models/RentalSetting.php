<?php

namespace App\Models;

use App\Services\RentalSettings;
use Database\Factories\RentalSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan aturan sewa (satu baris). Kolom kosong berarti memakai nilai bawaan dari `config/rental.php`.
 *
 * @see RentalSettings
 */
#[Fillable([
    'return_buffer_hours',
    'min_free_window_hours',
    'min_rental_hours',
    'max_rental_days',
    'pending_expiry_hours',
    'confirm_validation_window_hours',
    'verification_warning_hours',
    'vehicle_change_admin_threshold',
    'max_pending_orders',
    'max_vehicles_per_order',
])]
class RentalSetting extends Model
{
    /** @use HasFactory<RentalSettingFactory> */
    use HasFactory;

    /**
     * Baris pengaturan, dibuat kosong bila belum ada.
     */
    public static function current(): self
    {
        return self::query()->orderBy('id')->first() ?? self::create([]);
    }
}
