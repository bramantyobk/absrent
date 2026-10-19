<?php

use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\OrderStatus;
use App\VehicleStatus;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function isAvailableBetween(Vehicle $vehicle, string $start, string $end, ?int $ignoreOrderItemId = null): bool
{
    return Vehicle::query()
        ->availableBetween(Carbon::parse($start), Carbon::parse($end), $ignoreOrderItemId)
        ->whereKey($vehicle)
        ->exists();
}

it('holds a vehicle until the order end plus the return buffer', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-20 12:00');

    expect(isAvailableBetween($vehicle, '2026-10-20 14:59', '2026-10-21 08:00'))->toBeFalse()
        ->and(isAvailableBetween($vehicle, '2026-10-20 15:00', '2026-10-21 08:00'))->toBeTrue();
});

it('does not apply the buffer before an order starts', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 20:00', '2026-10-21 08:00');

    expect(isAvailableBetween($vehicle, '2026-10-20 10:00', '2026-10-20 20:00'))->toBeTrue()
        ->and(isAvailableBetween($vehicle, '2026-10-20 10:00', '2026-10-20 20:01'))->toBeFalse();
});

it('holds the vehicle only for pending, booking, and rented orders', function (OrderStatus $status, bool $holds) {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, $status, '2026-10-20 08:00', '2026-10-21 08:00');

    expect(isAvailableBetween($vehicle, '2026-10-20 09:00', '2026-10-20 10:00'))->toBe(! $holds);
})->with([
    'pending' => [OrderStatus::Pending, true],
    'booking' => [OrderStatus::Booking, true],
    'rented' => [OrderStatus::Rented, true],
    'completed' => [OrderStatus::Completed, false],
    'cancelled' => [OrderStatus::Cancelled, false],
    'failed' => [OrderStatus::Failed, false],
]);

it('releases a rented order that is not late after its end plus the buffer', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Rented, '2026-10-11 09:00', '2026-10-13 09:00');

    expect(isAvailableBetween($vehicle, '2026-10-13 11:59', '2026-10-14 09:00'))->toBeFalse()
        ->and(isAvailableBetween($vehicle, '2026-10-13 12:00', '2026-10-14 09:00'))->toBeTrue()
        ->and(isAvailableBetween($vehicle, '2026-10-14 09:00', '2026-10-15 09:00'))->toBeTrue();
});

it('keeps a late rented order holding the vehicle until it is completed', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Rented, '2026-10-05 08:00', '2026-10-07 08:00');

    expect(isAvailableBetween($vehicle, '2026-10-30 08:00', '2026-10-31 08:00'))->toBeFalse();

    $other = Vehicle::factory()->create();
    holdVehicle($other, OrderStatus::Booking, '2026-10-05 08:00', '2026-10-07 08:00');

    expect(isAvailableBetween($other, '2026-10-30 08:00', '2026-10-31 08:00'))->toBeTrue();
});

it('holds the vehicle during maintenance without a buffer after it', function () {
    $vehicle = Vehicle::factory()->create();
    VehicleSchedule::factory()->for($vehicle)->between('2026-10-20 09:00', '2026-10-20 12:00')->create();

    expect(isAvailableBetween($vehicle, '2026-10-20 11:59', '2026-10-21 08:00'))->toBeFalse()
        ->and(isAvailableBetween($vehicle, '2026-10-20 12:00', '2026-10-21 08:00'))->toBeTrue();
});

it('excludes vehicles hidden from the catalog and can ignore one order item', function () {
    $hidden = Vehicle::factory()->unavailable()->create();
    $vehicle = Vehicle::factory()->create();
    $item = holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-21 08:00');

    expect(isAvailableBetween($hidden, '2026-10-25 08:00', '2026-10-26 08:00'))->toBeFalse()
        ->and(isAvailableBetween($vehicle, '2026-10-20 09:00', '2026-10-20 10:00'))->toBeFalse()
        ->and(isAvailableBetween($vehicle, '2026-10-20 09:00', '2026-10-20 10:00', $item->id))->toBeTrue();
});

it('derives the display status from schedules, with rented ahead of servicing ahead of booked', function () {
    $available = Vehicle::factory()->create();
    $hidden = Vehicle::factory()->unavailable()->create();
    $booked = Vehicle::factory()->create();
    $servicing = Vehicle::factory()->create();
    $rented = Vehicle::factory()->create();
    $future = Vehicle::factory()->create();

    holdVehicle($booked, OrderStatus::Booking, '2026-10-12 08:00', '2026-10-13 08:00');
    VehicleSchedule::factory()->for($servicing)->between('2026-10-12 09:00', '2026-10-12 18:00')->create();
    holdVehicle($rented, OrderStatus::Rented, '2026-10-11 08:00', '2026-10-13 08:00');
    holdVehicle($future, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-21 08:00');

    $statuses = Vehicle::query()->withDisplayStatus()->get()->mapWithKeys(
        fn (Vehicle $vehicle) => [$vehicle->id => $vehicle->displayStatus()]
    );

    expect($statuses[$available->id])->toBe(VehicleStatus::Available)
        ->and($statuses[$hidden->id])->toBe(VehicleStatus::Hidden)
        ->and($statuses[$booked->id])->toBe(VehicleStatus::Booked)
        ->and($statuses[$servicing->id])->toBe(VehicleStatus::Servicing)
        ->and($statuses[$rented->id])->toBe(VehicleStatus::Rented)
        ->and($statuses[$future->id])->toBe(VehicleStatus::Available)
        ->and($rented->displayStatus())->toBe(VehicleStatus::Rented);
});

it('marks a rented vehicle as not returned once its return buffer has passed, and as rented before that', function () {
    $late = Vehicle::factory()->create();
    $onTime = Vehicle::factory()->create();
    $justEnded = Vehicle::factory()->create();
    holdVehicle($late, OrderStatus::Rented, '2026-10-05 08:00', '2026-10-07 08:00');
    holdVehicle($onTime, OrderStatus::Rented, '2026-10-11 08:00', '2026-10-13 08:00');
    holdVehicle($justEnded, OrderStatus::Rented, '2026-10-10 08:00', '2026-10-12 08:00');

    $statuses = Vehicle::query()->withDisplayStatus()->get()->mapWithKeys(fn (Vehicle $vehicle) => [$vehicle->id => $vehicle->displayStatus()]);

    expect($statuses[$late->id])->toBe(VehicleStatus::Overdue)
        ->and($statuses[$onTime->id])->toBe(VehicleStatus::Rented)
        ->and($statuses[$justEnded->id])->toBe(VehicleStatus::Rented)
        ->and($late->displayStatus())->toBe(VehicleStatus::Overdue);
});
