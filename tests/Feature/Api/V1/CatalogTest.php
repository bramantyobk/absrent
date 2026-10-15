<?php

use App\Models\EquipmentItem;
use App\Models\Vehicle;
use App\OrderStatus;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

function quoteUrl(Vehicle $vehicle, array $query): string
{
    return route('api.v1.vehicles.quote', ['vehicle' => $vehicle] + $query);
}

it('serves the catalog without login and hides unlisted vehicles and private fields', function () {
    Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio', 'plate_number' => 'B 9876 XY', 'odometer_km' => 12345]);
    Vehicle::factory()->unavailable()->create(['model' => 'Tersembunyi']);

    $response = $this->getJson(route('api.v1.vehicles.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.model', 'Genio')
        ->assertJsonPath('data.0.max_rental_days', 7)
        ->assertJsonPath('data.0.min_rental_hours', 24)
        ->assertJsonMissingPath('data.0.plate_number')
        ->assertJsonMissingPath('data.0.odometer_km');

    expect($response->json('data.0.photo_url'))->toContain('vehicle-placeholder.png');
});

it('filters the catalog by category and by free range', function () {
    $free = Vehicle::factory()->motorcycle()->create(['model' => 'Bebas']);
    $booked = Vehicle::factory()->motorcycle()->create(['model' => 'Dipesan']);
    Vehicle::factory()->car()->create(['model' => 'Mobil']);
    holdVehicle($booked, OrderStatus::Booking, '2026-10-14 08:00', '2026-10-15 08:00');

    $this->getJson(route('api.v1.vehicles.index', ['type' => 'motorcycle', 'start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-16T09:00']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.model', 'Bebas');

    $this->getJson(route('api.v1.vehicles.index', ['start_at' => '2026-10-14T09:00']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_at');
});

it('shows the vehicle with its active equipment and 404 for unlisted vehicles', function () {
    $vehicle = Vehicle::factory()->motorcycle()->create(['max_rental_days' => 3]);
    EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'quantity' => 2]);
    EquipmentItem::factory()->motorcycle()->inactive()->create(['name' => 'Kunci cadangan']);
    EquipmentItem::factory()->car()->create(['name' => 'Dongkrak']);

    $this->getJson(route('api.v1.vehicles.show', $vehicle))
        ->assertOk()
        ->assertJsonPath('data.max_rental_days', 3)
        ->assertJsonCount(1, 'data.equipment')
        ->assertJsonPath('data.equipment.0', ['name' => 'Helm SNI', 'quantity' => 2]);

    $this->getJson(route('api.v1.vehicles.show', Vehicle::factory()->unavailable()->create()))->assertNotFound();
});

it('quotes days and subtotal for a valid free range', function () {
    $vehicle = Vehicle::factory()->create(['price_per_day' => 75000]);

    $this->getJson(quoteUrl($vehicle, ['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-16T10:00']))
        ->assertOk()
        ->assertJsonPath('data.days', 3)
        ->assertJsonPath('data.unit_price', 75000)
        ->assertJsonPath('data.subtotal', 225000)
        ->assertJsonPath('data.available', true);
});

it('rejects a quote that breaks the rental rules or collides with a booking', function (array $query, string $field) {
    $vehicle = Vehicle::factory()->create(['max_rental_days' => 5]);
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-21 08:00');

    $this->getJson(quoteUrl($vehicle, $query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'past start' => [['start_at' => '2026-10-11T09:00', 'end_at' => '2026-10-13T09:00'], 'start_at'],
    'under 24 hours' => [['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-15T08:00'], 'end_at'],
    'over the vehicle limit' => [['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-19T09:01'], 'end_at'],
    'overlapping a booking' => [['start_at' => '2026-10-19T09:00', 'end_at' => '2026-10-21T09:00'], 'start_at'],
    'missing dates' => [[], 'start_at'],
]);

it('does not quote an unlisted vehicle', function () {
    $this->getJson(quoteUrl(Vehicle::factory()->unavailable()->create(), ['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-16T09:00']))->assertNotFound();
});
