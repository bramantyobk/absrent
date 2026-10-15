<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\OrderVehicleChange;
use App\Models\User;
use App\Models\Vehicle;
use App\OldVehicleStatus;
use App\OrderStatus;
use App\VehicleChangeReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderVehicleChange>
 */
class OrderVehicleChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'from_vehicle_id' => Vehicle::factory(),
            'to_vehicle_id' => Vehicle::factory(),
            'reason' => VehicleChangeReason::CustomerRequest,
            'from_order_status' => OrderStatus::Booking,
            'note' => null,
            'old_unit_price' => 75000,
            'new_unit_price' => 75000,
            'suggested_amount' => 0,
            'final_amount' => 0,
            'old_vehicle_status' => null,
            'changed_by' => User::factory()->operator(),
        ];
    }

    /**
     * Unit lama belum diinspeksi dan tetap menahan jadwalnya.
     */
    public function oldVehiclePending(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_order_status' => OrderStatus::Rented,
            'old_vehicle_status' => OldVehicleStatus::Pending,
            'old_vehicle_note' => 'Unit masih di lokasi pelanggan.',
            'old_vehicle_status_by' => User::factory()->operator(),
            'old_vehicle_status_at' => now(),
        ]);
    }

    public function oldVehicleLost(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_order_status' => OrderStatus::Rented,
            'old_vehicle_status' => OldVehicleStatus::Lost,
            'old_vehicle_note' => 'Unit hilang.',
            'old_vehicle_status_by' => User::factory()->admin(),
            'old_vehicle_status_at' => now(),
        ]);
    }
}
