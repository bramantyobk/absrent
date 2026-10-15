<?php

use App\Models\CompanyProfile;
use App\Models\EquipmentItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\OrderStatus;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

it('shows only vehicles listed in the catalog on the home page, with company info in the footer', function () {
    CompanyProfile::factory()->create(['name' => 'Rental Maju', 'address' => 'Jl. Kenanga 5', 'support_whatsapp' => '6281200000000']);
    Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);
    Vehicle::factory()->unavailable()->create(['brand' => 'Yamaha', 'model' => 'Tersembunyi']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Genio')
        ->assertDontSee('Tersembunyi')
        ->assertSee('Rental Maju')
        ->assertSee('Jl. Kenanga 5')
        ->assertSee('Jam layanan kantor')
        ->assertSee('Senin')
        ->assertSee('Minggu')
        ->assertSee('Libur')
        ->assertSee('https://wa.me/6281200000000', false);
});

it('lists the catalog for guests without exposing plate numbers', function () {
    Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio', 'plate_number' => 'B 9876 XY']);

    $this->get(route('vehicles.index'))
        ->assertOk()
        ->assertSee('Genio')
        ->assertDontSee('B 9876 XY');
});

it('filters the catalog by search, category, and drive type', function () {
    Vehicle::factory()->motorcycle()->create(['model' => 'Genio', 'drive_type' => 'automatic']);
    Vehicle::factory()->motorcycle()->create(['model' => 'Tiger', 'drive_type' => 'manual']);
    Vehicle::factory()->car()->create(['model' => 'Avanza', 'drive_type' => 'manual']);

    $models = fn (array $query) => $this->get(route('vehicles.index', $query))->assertOk()->viewData('vehicles')->pluck('model')->sort()->values()->all();

    expect($models([]))->toBe(['Avanza', 'Genio', 'Tiger'])
        ->and($models(['search' => 'gen']))->toBe(['Genio'])
        ->and($models(['type' => 'car']))->toBe(['Avanza'])
        ->and($models(['drive_type' => 'manual']))->toBe(['Avanza', 'Tiger'])
        ->and($models(['type' => 'motorcycle', 'drive_type' => 'manual']))->toBe(['Tiger']);
});

it('hides vehicles that are busy during the chosen range', function () {
    $free = Vehicle::factory()->create(['model' => 'Bebas']);
    $booked = Vehicle::factory()->create(['model' => 'Dipesan']);
    $servicing = Vehicle::factory()->create(['model' => 'Servis']);
    holdVehicle($booked, OrderStatus::Booking, '2026-10-14 08:00', '2026-10-15 08:00');
    VehicleSchedule::factory()->for($servicing)->between('2026-10-14 08:00', '2026-10-15 08:00')->create();

    $models = $this->get(route('vehicles.index', ['start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-16T09:00']))
        ->assertOk()
        ->viewData('vehicles')
        ->pluck('model')
        ->all();

    expect($models)->toBe(['Bebas']);
});

it('requires both dates to filter the catalog by range', function () {
    $this->get(route('vehicles.index', ['start_at' => '2026-10-14T09:00']))->assertSessionHasErrors('end_at');
});

it('shows the vehicle detail with specs and active equipment of its category only', function () {
    $vehicle = Vehicle::factory()->motorcycle()->create(['brand' => 'Honda', 'model' => 'Genio', 'plate_number' => 'B 1111 ZZ', 'drive_type' => 'automatic', 'energy_capacity' => 4.2, 'description' => 'Irit dan lincah']);
    EquipmentItem::factory()->motorcycle()->create(['name' => 'Helm SNI', 'quantity' => 2, 'sort_order' => 1]);
    EquipmentItem::factory()->motorcycle()->inactive()->create(['name' => 'Kunci cadangan']);
    EquipmentItem::factory()->car()->create(['name' => 'Dongkrak']);

    $this->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertSee('Honda Genio')
        ->assertSee('Irit dan lincah')
        ->assertSee('4,2 liter')
        ->assertSee('Helm SNI')
        ->assertDontSee('Kunci cadangan')
        ->assertDontSee('Dongkrak')
        ->assertDontSee('B 1111 ZZ')
        ->assertSee('24 jam sampai 7 hari');
});

it('shows the effective rental limit of the vehicle', function () {
    $vehicle = Vehicle::factory()->create(['max_rental_days' => 3]);

    $this->get(route('vehicles.show', $vehicle))->assertSee('24 jam sampai 3 hari');
});

it('returns 404 for a vehicle hidden from the catalog', function () {
    $this->get(route('vehicles.show', Vehicle::factory()->unavailable()->create()))->assertNotFound();
});

it('offers the right call to action to guests, customers, and staff', function () {
    $vehicle = Vehicle::factory()->create();

    $this->get(route('vehicles.show', $vehicle))->assertSee('Masuk untuk memesan')->assertDontSee('Lanjut ke pesanan');

    $this->actingAs(User::factory()->create())->get(route('vehicles.show', $vehicle))->assertSee('Lanjut ke pesanan');

    $this->actingAs(User::factory()->operator()->create())->get(route('vehicles.show', $vehicle))
        ->assertSee('Akun staf tidak dapat membuat pesanan')
        ->assertDontSee('Lanjut ke pesanan');
});

it('prefills the schedule from the query string', function () {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => '2026-10-14T09:00', 'end_at' => '2026-10-16T09:00']))
        ->assertSee('value="2026-10-14T09:00"', false)
        ->assertSee('value="2026-10-16T09:00"', false);
});

it('links the support button to the company whatsapp number', function () {
    CompanyProfile::factory()->create(['support_whatsapp' => '6281200000000']);
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);

    $this->get(route('vehicles.show', $vehicle))
        ->assertSee('Tanya via WhatsApp')
        ->assertSee('https://wa.me/6281200000000?text=Halo%2C%20saya%20ingin%20bertanya%20tentang%20Honda%20Genio.', false);
});
