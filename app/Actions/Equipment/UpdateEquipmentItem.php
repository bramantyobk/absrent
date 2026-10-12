<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentItem;

class UpdateEquipmentItem
{
    /**
     * Urutan yang dikosongkan tidak mengubah urutan yang ada. Riwayat inspeksi tidak terpengaruh karena memakai snapshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(EquipmentItem $equipmentItem, array $data): EquipmentItem
    {
        if (($data['sort_order'] ?? null) === null) {
            unset($data['sort_order']);
        }

        $equipmentItem->update($data);

        return $equipmentItem;
    }
}
