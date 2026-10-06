<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vehicle_id' => Vehicle::factory(),
            'start_at' => fn () => now()->addDays(fake()->numberBetween(1, 14))->setTime(8, 0),
            'days' => fake()->numberBetween(1, 5),
            'end_at' => fn (array $attributes) => Carbon::parse($attributes['start_at'])->addDays($attributes['days']),
            'unit_price' => fn (array $attributes) => Vehicle::findOrFail($attributes['vehicle_id'])->price_per_day,
            'subtotal' => fn (array $attributes) => $attributes['days'] * $attributes['unit_price'],
        ];
    }
}
