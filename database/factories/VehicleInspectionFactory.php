<?php

namespace Database\Factories;

use App\InspectionType;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleInspection>
 */
class VehicleInspectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'order_item_id' => null,
            'type' => InspectionType::Manual,
            'odometer_km' => fake()->numberBetween(1000, 60000),
            'energy_level' => null,
            'equipment_checks' => null,
            'note' => null,
            'inspected_by' => null,
            'inspected_at' => now(),
        ];
    }
}
