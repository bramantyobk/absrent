<?php

namespace Database\Factories;

use App\Models\RentalSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalSetting>
 */
class RentalSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'return_buffer_hours' => 3,
            'min_free_window_hours' => 24,
            'min_rental_hours' => 24,
            'max_rental_days' => 7,
            'pending_expiry_hours' => 24,
            'confirm_validation_window_hours' => 24,
            'verification_warning_hours' => 6,
            'vehicle_change_admin_threshold' => 100000,
            'max_pending_orders' => 2,
            'max_vehicles_per_order' => 2,
        ];
    }
}
