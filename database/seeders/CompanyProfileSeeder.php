<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;

class CompanyProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanyProfile::factory()->create([
            'name' => 'ABSRENT Rental',
            'address' => 'Jl. Contoh Raya No. 1, Jakarta',
            'support_whatsapp' => '6281200000000',
        ]);
    }
}
