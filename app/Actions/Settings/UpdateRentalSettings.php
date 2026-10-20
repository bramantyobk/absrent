<?php

namespace App\Actions\Settings;

use App\Models\RentalSetting;
use App\Services\RentalSettings;

class UpdateRentalSettings
{
    public function __construct(private RentalSettings $rentalSettings) {}

    /**
     * Menyimpan pengaturan lalu langsung menerapkannya. Berlaku untuk pesanan berikutnya: `expires_at` pesanan yang sudah
     * dibuat tidak dihitung ulang, sedangkan jeda memengaruhi pengecekan ketersediaan seterusnya.
     *
     * @param  array<string, int>  $values
     */
    public function handle(RentalSetting $rentalSetting, array $values): RentalSetting
    {
        $rentalSetting->update($values);

        $this->rentalSettings->refresh();

        return $rentalSetting;
    }
}
