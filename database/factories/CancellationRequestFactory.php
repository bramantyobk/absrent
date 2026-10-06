<?php

namespace Database\Factories;

use App\CancellationStatus;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CancellationRequest>
 */
class CancellationRequestFactory extends Factory
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
            'user_id' => fn (array $attributes) => Order::findOrFail($attributes['order_id'])->user_id,
            'reason' => fake()->randomElement([
                'Jadwal perjalanan berubah.',
                'Ada keperluan mendadak.',
                'Salah memilih kendaraan.',
            ]),
            'status' => CancellationStatus::Pending,
            'handled_by' => null,
            'handled_note' => null,
            'handled_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CancellationStatus::Pending,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CancellationStatus::Approved,
            'handled_by' => User::factory()->operator(),
            'handled_note' => 'Disetujui, refund diproses di luar aplikasi.',
            'handled_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CancellationStatus::Rejected,
            'handled_by' => User::factory()->operator(),
            'handled_note' => 'Ditolak, pesanan sudah mendekati jadwal sewa.',
            'handled_at' => now(),
        ]);
    }
}
