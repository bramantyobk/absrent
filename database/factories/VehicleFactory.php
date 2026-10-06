<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @var array<int, array{0: string, 1: string, 2: int}>
     */
    private const MOTORCYCLES = [
        ['Honda', 'Genio', 75000],
        ['Honda', 'Beat', 70000],
        ['Honda', 'Vario 125', 85000],
        ['Yamaha', 'NMAX', 120000],
        ['Yamaha', 'Aerox', 110000],
    ];

    /**
     * @var array<int, array{0: string, 1: string, 2: int}>
     */
    private const CARS = [
        ['Toyota', 'Avanza', 350000],
        ['Daihatsu', 'Xenia', 330000],
        ['Honda', 'Brio', 300000],
        ['Mitsubishi', 'Xpander', 400000],
        ['Toyota', 'Innova Zenix', 600000],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->catalogAttributes(
            fake()->boolean(60) ? VehicleType::Motorcycle : VehicleType::Car
        );
    }

    public function motorcycle(): static
    {
        return $this->state(fn (array $attributes) => $this->catalogAttributes(VehicleType::Motorcycle));
    }

    public function car(): static
    {
        return $this->state(fn (array $attributes) => $this->catalogAttributes(VehicleType::Car));
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogAttributes(VehicleType $type): array
    {
        [$brand, $model, $pricePerDay] = fake()->randomElement(
            $type === VehicleType::Motorcycle ? self::MOTORCYCLES : self::CARS
        );

        return [
            'brand' => $brand,
            'model' => $model,
            'plate_number' => 'B '.fake()->unique()->numerify('####').' '.strtoupper(fake()->lexify('??')),
            'type' => $type,
            'year' => fake()->numberBetween(2020, 2025),
            'color' => fake()->randomElement(['Hitam', 'Putih', 'Merah', 'Silver', 'Biru']),
            'price_per_day' => $pricePerDay,
            'price_per_hour' => (int) round($pricePerDay / 10, -3),
            'photo' => null,
            'is_available' => true,
        ];
    }
}
