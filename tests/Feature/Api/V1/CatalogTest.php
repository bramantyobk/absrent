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

it('filters the catalog by category and by free period', function () {
    $free = Vehicle::factory()->motorcycle()->create(['model' => 'Bebas']);
    $booked = Vehicle::factory()->motorcycle()->create(['model' => 'Dipesan']);
    Vehicle::factory()->car()->create(['model' => 'Mobil']);
    holdVehicle($booked, OrderStatus::Booking, '2026-10-14 08:00', '2026-10-15 08:00');

    $this->getJson(route('api.v1.vehicles.index', ['type' => 'motorcycle', 'start_at' => '2026-10-14T09:00', 'days' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.model', 'Bebas');

    $this->getJson(route('api.v1.vehicles.index', ['start_at' => '2026-10-14T09:00']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('days');
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

it('quotes the return time and subtotal for a valid free period', function () {
    $vehicle = Vehicle::factory()->create(['price_per_day' => 75000]);

    $this->getJson(quoteUrl($vehicle, ['start_at' => '2026-10-14T09:00', 'days' => 3]))
        ->assertOk()
        ->assertJsonPath('data.days', 3)
        ->assertJsonPath('data.unit_price', 75000)
        ->assertJsonPath('data.subtotal', 225000)
        ->assertJsonPath('data.end_at', '2026-10-17T09:00:00+07:00')
        ->assertJsonPath('data.available', true);
});

it('rejects a quote that breaks the rental rules or collides with a booking', function (array $query, string $field) {
    $vehicle = Vehicle::factory()->create(['max_rental_days' => 5]);
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 08:00', '2026-10-21 08:00');

    $this->getJson(quoteUrl($vehicle, $query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'past start' => [['start_at' => '2026-10-11T09:00', 'days' => 2], 'start_at'],
    'over the vehicle limit' => [['start_at' => '2026-10-14T09:00', 'days' => 6], 'days'],
    'overlapping a booking' => [['start_at' => '2026-10-19T09:00', 'days' => 2], 'start_at'],
    'ending inside the return buffer before a booking' => [['start_at' => '2026-10-17T05:01', 'days' => 3], 'start_at'],
    'missing days' => [['start_at' => '2026-10-14T09:00'], 'days'],
]);

it('does not quote an unlisted vehicle', function () {
    $this->getJson(quoteUrl(Vehicle::factory()->unavailable()->create(), ['start_at' => '2026-10-14T09:00', 'days' => 2]))->assertNotFound();
});

it('lists the durations that fit before the next booking, with the return time of each', function () {
    $vehicle = Vehicle::factory()->create(['price_per_day' => 100000]);
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 12:00', '2026-10-21 12:00');

    $response = $this->getJson(route('api.v1.vehicles.return-options', ['vehicle' => $vehicle, 'start_at' => '2026-10-17T09:00']))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.days', 1)
        ->assertJsonPath('data.0.end_at', '2026-10-18T09:00:00+07:00')
        ->assertJsonPath('data.0.subtotal', 100000)
        ->assertJsonPath('data.2.days', 3);

    expect($response->json('data.0.end_label'))->toContain('18/10/2026 09:00 WIB');

    $this->getJson(route('api.v1.vehicles.return-options', ['vehicle' => $vehicle, 'start_at' => '2026-10-17T09:01']))
        ->assertJsonCount(2, 'data');
});

it('never lists more durations than the vehicle limit and lists none when the start itself is taken', function () {
    $limited = Vehicle::factory()->create(['max_rental_days' => 3]);
    $taken = Vehicle::factory()->create();
    holdVehicle($taken, OrderStatus::Booking, '2026-10-14 08:00', '2026-10-15 08:00');

    $this->getJson(route('api.v1.vehicles.return-options', ['vehicle' => $limited, 'start_at' => '2026-10-14T09:00']))->assertJsonCount(3, 'data');
    $this->getJson(route('api.v1.vehicles.return-options', ['vehicle' => $taken, 'start_at' => '2026-10-14T09:00']))->assertOk()->assertJsonCount(0, 'data');
});

it('validates the start of the duration options', function () {
    $vehicle = Vehicle::factory()->create();

    $this->getJson(route('api.v1.vehicles.return-options', ['vehicle' => $vehicle]))->assertUnprocessable()->assertJsonValidationErrors('start_at');
    $this->getJson(route('api.v1.vehicles.return-options', ['vehicle' => $vehicle, 'start_at' => '2026-10-11T09:00']))->assertUnprocessable()->assertJsonValidationErrors('start_at');
    $this->getJson(route('api.v1.vehicles.return-options', ['vehicle' => Vehicle::factory()->unavailable()->create(), 'start_at' => '2026-10-14T09:00']))->assertNotFound();
});

it('converts a start time with an offset to the company time zone for the options', function () {
    config(['app.timezone' => 'Asia/Jakarta']);
    date_default_timezone_set('Asia/Jakarta');
    $vehicle = Vehicle::factory()->create();

    $this->getJson(route('api.v1.vehicles.return-options', ['vehicle' => $vehicle, 'start_at' => '2026-10-14T09:00:00+08:00']))
        ->assertJsonPath('data.0.end_at', '2026-10-15T08:00:00+07:00');
});
