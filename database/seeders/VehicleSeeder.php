<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Vehicle::factory()->motorcycle()->count(6)->create();
        Vehicle::factory()->car()->count(5)->create();
        Vehicle::factory()->motorcycle()->unavailable()->create();
    }
}
