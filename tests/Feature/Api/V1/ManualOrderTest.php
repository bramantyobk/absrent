<?php

use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
    Storage::fake('local');
});

it('rejects guests and customers on the manual order endpoints', function (string $method, string $route) {
    $this->{$method}(route($route))->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->{$method}(route($route))->assertForbidden();
})->with([
    ['postJson', 'api.v1.dashboard.orders.manual.store'],
    ['getJson', 'api.v1.dashboard.customers.lookup'],
]);

it('looks up customers through the api', function () {
    $customer = User::factory()->create(['name' => 'Budi Santoso', 'phone' => '6281234567890', 'phone_flagged_at' => now()]);
    Sanctum::actingAs(User::factory()->operator()->create());

    $this->getJson(route('api.v1.dashboard.customers.lookup', ['phone' => '081234567890']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $customer->id)
        ->assertJsonPath('data.0.phone_flagged', true);

    $this->getJson(route('api.v1.dashboard.customers.lookup'))->assertUnprocessable();
});

it('creates a paid manual booking for a new customer through the api', function () {
    $staff = User::factory()->operator()->create();
    $vehicle = Vehicle::factory()->create(['price_per_day' => 75000]);
    Sanctum::actingAs($staff);

    $this->postJson(route('api.v1.dashboard.orders.manual.store'), [
        'start_at' => '2026-10-14T09:00',
        'days' => 2,
        'items' => [['vehicle_id' => $vehicle->id]],
        'new_customer' => ['name' => 'Budi Santoso', 'phone' => '081234567890', 'email' => 'budi@example.test'],
        'payment_note' => 'Tunai',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'booking')
        ->assertJsonPath('data.total_price', 150000)
        ->assertJsonPath('data.is_manual', true)
        ->assertJsonPath('data.created_by', $staff->name)
        ->assertJsonPath('data.customer.name', 'Budi Santoso')
        ->assertJsonPath('data.transaction.status', 'paid');

    expect(Order::sole()->transaction->status)->toBe(TransactionStatus::Paid)->and(User::where('email', 'budi@example.test')->exists())->toBeTrue();
});

it('creates a manual booking for an existing customer with a proof, and validates the payload', function () {
    $customer = User::factory()->create();
    $vehicle = Vehicle::factory()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.dashboard.orders.manual.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['start_at', 'days', 'items', 'new_customer']);

    $this->post(route('api.v1.dashboard.orders.manual.store'), [
        'start_at' => '2026-10-14T09:00',
        'days' => 1,
        'items' => [['vehicle_id' => $vehicle->id]],
        'customer_id' => $customer->id,
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.customer.id', $customer->id);

    expect(Order::sole()->status)->toBe(OrderStatus::Booking)->and(Order::sole()->transaction->proof_path)->toStartWith('payment-proofs/');

    $this->postJson(route('api.v1.dashboard.orders.manual.store'), [
        'start_at' => '2026-10-14T09:00',
        'days' => 1,
        'items' => [['vehicle_id' => $vehicle->id]],
        'customer_id' => $customer->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('items.0.vehicle_id');
});
