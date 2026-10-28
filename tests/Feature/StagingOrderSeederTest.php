<?php

use App\InspectionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\OrderStatus;
use App\Services\RevenueReport;
use Database\Seeders\EquipmentItemSeeder;
use Database\Seeders\StagingOrderSeeder;

beforeEach(function () {
    User::factory()->admin()->create();
    Vehicle::factory()->motorcycle()->count(4)->create();
    Vehicle::factory()->car()->count(3)->create();
    $this->seed(EquipmentItemSeeder::class);
});

it('is not part of the default database seeder', function () {
    $this->seed();

    expect(Order::where('code', 'like', 'ABS-DM%')->exists())->toBeFalse();
});

it('seeds orders with pickup and return inspections and revenue for the dashboard', function () {
    $this->seed(StagingOrderSeeder::class);

    $completed = Order::where('status', OrderStatus::Completed)->with('items.inspections')->get();

    expect($completed)->not->toBeEmpty()
        ->and(Order::where('status', OrderStatus::Rented)->exists())->toBeTrue()
        ->and(Order::where('status', OrderStatus::Booking)->exists())->toBeTrue();

    $completed->each(function (Order $order) {
        $inspections = $order->items->first()->inspections->keyBy(fn (VehicleInspection $inspection) => $inspection->type->value);

        expect($inspections)->toHaveCount(2)
            ->and($inspections[InspectionType::Return->value]->odometer_km)->toBeGreaterThan($inspections[InspectionType::Pickup->value]->odometer_km)
            ->and($inspections[InspectionType::Pickup->value]->equipment_checks)->not->toBeEmpty()
            ->and($order->total_price)->toBe((int) $order->items->sum('subtotal'));
    });

    expect(app(RevenueReport::class)->daily(now()->subDays(30)->startOfDay(), now()->endOfDay())->sum('total'))->toBeGreaterThan(0);
});

it('skips when sample orders already exist', function () {
    $this->seed(StagingOrderSeeder::class);
    $count = Order::count();

    $this->seed(StagingOrderSeeder::class);

    expect(Order::count())->toBe($count);
});

it('requires at least two vehicles', function () {
    Vehicle::query()->delete();

    $this->seed(StagingOrderSeeder::class);
})->throws(RuntimeException::class);

it('avoids schedules held by existing orders', function () {
    $held = Vehicle::first();
    $existing = holdVehicle($held, OrderStatus::Booking, now()->subDays(40)->toDateTimeString(), now()->addDays(40)->toDateTimeString());

    $this->seed(StagingOrderSeeder::class);

    expect(OrderItem::where('vehicle_id', $held->id)->whereHas('order', fn ($query) => $query->whereIn('status', OrderStatus::holdingSchedule()))->pluck('id')->all())->toBe([$existing->id])
        ->and(Order::where('code', 'like', 'ABS-DM%')->exists())->toBeTrue();
});
