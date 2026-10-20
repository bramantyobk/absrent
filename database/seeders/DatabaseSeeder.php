<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CompanyProfileSeeder::class,
            BankAccountSeeder::class,
            VehicleSeeder::class,
            EquipmentItemSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
