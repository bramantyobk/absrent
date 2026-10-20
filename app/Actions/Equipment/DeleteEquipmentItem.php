<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentItem;

class DeleteEquipmentItem
{
    /**
     * Menghapus perlengkapan secara permanen. Aman bagi riwayat: inspeksi menyimpan snapshot ceklis (id dan nama) sendiri,
     * tanpa foreign key ke master perlengkapan. Menonaktifkan tetap tersedia lewat ubah.
     */
    public function handle(EquipmentItem $equipmentItem): void
    {
        $equipmentItem->delete();
    }
}
