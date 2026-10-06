<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'bank_account_id' => null,
            'amount' => fn (array $attributes) => Order::findOrFail($attributes['order_id'])->total_price,
            'proof_path' => null,
            'status' => TransactionStatus::Pending,
            'note' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Pending,
        ]);
    }

    public function verifying(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Verifying,
            ...$this->proofAttributes(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Paid,
            'verified_by' => User::factory()->operator(),
            'verified_at' => now(),
            ...$this->proofAttributes(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Failed,
            'note' => 'Bukti transfer tidak sesuai dengan nominal pesanan.',
            'verified_by' => User::factory()->operator(),
            'verified_at' => now(),
            ...$this->proofAttributes(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Cancelled,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function proofAttributes(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'proof_path' => 'payment-proofs/'.fake()->uuid().'.jpg',
        ];
    }
}
