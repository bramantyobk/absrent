<?php

namespace Database\Factories;

use App\Models\EquipmentItem;
use App\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentItem>
 */
class EquipmentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'category' => VehicleType::Motorcycle,
            'quantity' => 1,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function motorcycle(): static
    {
        return $this->state(fn (array $attributes) => ['category' => VehicleType::Motorcycle]);
    }

    public function car(): static
    {
        return $this->state(fn (array $attributes) => ['category' => VehicleType::Car]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
