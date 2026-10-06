<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin ABSRENT',
            'email' => 'admin@absrent.test',
            'phone' => '6281200000001',
        ]);

        User::factory()->operator()->onDuty()->create([
            'name' => 'Operator Satu',
            'email' => 'operator1@absrent.test',
            'phone' => '6281200000002',
        ]);

        User::factory()->operator()->onDuty()->create([
            'name' => 'Operator Dua',
            'email' => 'operator2@absrent.test',
            'phone' => '6281200000003',
        ]);

        User::factory()->operator()->create([
            'name' => 'Operator Tiga',
            'email' => 'operator3@absrent.test',
            'phone' => '6281200000004',
        ]);

        User::factory()->create([
            'name' => 'User Demo',
            'email' => 'user@absrent.test',
            'phone' => '6281200000005',
        ]);

        User::factory()->count(5)->create();
    }
}
