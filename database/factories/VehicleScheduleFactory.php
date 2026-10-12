<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\VehicleScheduleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleSchedule>
 */
class VehicleScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = now()->addDays(fake()->numberBetween(1, 30))->setTime(9, 0);

        return [
            'vehicle_id' => Vehicle::factory(),
            'type' => VehicleScheduleType::Maintenance,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(fake()->numberBetween(4, 48)),
            'note' => fake()->optional()->sentence(4),
            'odometer_km' => null,
            'created_by' => null,
        ];
    }

    public function between(string $startAt, string $endAt): static
    {
        return $this->state(fn (array $attributes) => [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
    }
}
