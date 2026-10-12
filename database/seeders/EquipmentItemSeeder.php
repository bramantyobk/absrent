<?php

namespace Database\Seeders;

use App\Models\EquipmentItem;
use App\VehicleType;
use Illuminate\Database\Seeder;

class EquipmentItemSeeder extends Seeder
{
    /**
     * @var array<string, array<int, array{0: string, 1: int}>>
     */
    private const ITEMS = [
        'motorcycle' => [
            ['Helm SNI', 2],
            ['Jas hujan', 2],
            ['STNK', 1],
            ['Kunci kontak', 1],
        ],
        'car' => [
            ['STNK', 1],
            ['Kunci kontak', 2],
            ['Dongkrak', 1],
            ['Ban serep', 1],
            ['Segitiga pengaman', 1],
            ['Kotak P3K', 1],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::ITEMS as $category => $items) {
            foreach ($items as $index => [$name, $quantity]) {
                EquipmentItem::query()->updateOrCreate(
                    ['category' => VehicleType::from($category), 'name' => $name],
                    ['quantity' => $quantity, 'is_active' => true, 'sort_order' => $index + 1],
                );
            }
        }
    }
}
