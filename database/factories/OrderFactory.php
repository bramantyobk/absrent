<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ABS-'.strtoupper(fake()->unique()->bothify('??######')),
            'user_id' => User::factory(),
            'assigned_operator_id' => null,
            'total_price' => 0,
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);
    }

    public function booking(): static
    {
        return $this->withStatus(OrderStatus::Booking);
    }

    public function rented(): static
    {
        return $this->withStatus(OrderStatus::Rented);
    }

    public function completed(): static
    {
        return $this->withStatus(OrderStatus::Completed);
    }

    public function cancelled(): static
    {
        return $this->withStatus(OrderStatus::Cancelled);
    }

    public function failed(): static
    {
        return $this->withStatus(OrderStatus::Failed);
    }

    private function withStatus(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'expires_at' => null,
        ]);
    }
}
