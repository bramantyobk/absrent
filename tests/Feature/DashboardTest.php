<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\OrderStatus;

it('shows the dashboard shell and navigation to staff', function () {
    $operator = User::factory()->operator()->create(['name' => 'Rina Operator']);

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Rina Operator')
        ->assertSee('Main Menu')
        ->assertSee('5 Top Orderan Rental')
        ->assertSee('Status Pesanan')
        ->assertSee('Pendapatan');
});

it('shows vehicle counts based on order status', function () {
    $this->actingAs(User::factory()->admin()->create());

    Vehicle::factory()->create();
    $rentedVehicle = Vehicle::factory()->create();
    $bookedVehicle = Vehicle::factory()->create();
    Vehicle::factory()->unavailable()->create();

    OrderItem::factory()
        ->for(Order::factory()->rented()->create(), 'order')
        ->for($rentedVehicle, 'vehicle')
        ->create();
    OrderItem::factory()
        ->for(Order::factory()->booking()->create(), 'order')
        ->for($bookedVehicle, 'vehicle')
        ->create();

    $stats = $this->get(route('dashboard'))->viewData('vehicleStats');

    expect($stats)->toBe(['total' => 4, 'available' => 2, 'booked' => 1, 'rented' => 1]);
});

it('lists the latest orders with vehicle and status', function () {
    $this->actingAs(User::factory()->admin()->create());

    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $order = Order::factory()->create(['status' => OrderStatus::Booking, 'total_price' => 150000]);
    OrderItem::factory()->for($order, 'order')->for($vehicle, 'vehicle')->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Honda Genio')
        ->assertSee($order->code)
        ->assertSee('Rp150.000')
        ->assertSee('Booking');
});

it('shows an empty state when there are no orders', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada pesanan.');
});

it('shows the Pengguna menu to admins and operators', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('dashboard'))
        ->assertSee('Pengguna');
})->with(['admin', 'operator']);
