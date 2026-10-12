<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentItem;
use App\VehicleType;

class CreateEquipmentItem
{
    /**
     * Urutan yang dikosongkan ditempatkan di akhir daftar kategorinya.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): EquipmentItem
    {
        $data['is_active'] ??= true;
        $data['sort_order'] ??= EquipmentItem::query()
            ->forCategory(VehicleType::from($data['category']))
            ->max('sort_order') + 1;

        return EquipmentItem::create($data);
    }
}
