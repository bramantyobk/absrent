<?php

use App\Models\CompanyProfile;
use App\Models\EquipmentItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\OrderStatus;
use App\Services\CompanyProfileResolver;
use Illuminate\Support\Facades\DB;

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

    $models = $this->get(route('vehicles.index', ['start_at' => '2026-10-14T09:00', 'days' => 2]))
        ->assertOk()
        ->viewData('vehicles')
        ->pluck('model')
        ->all();

    expect($models)->toBe(['Bebas']);
});

it('requires both the start time and the duration to filter the catalog by period', function () {
    $this->get(route('vehicles.index', ['start_at' => '2026-10-14T09:00']))->assertSessionHasErrors('days');
    $this->get(route('vehicles.index', ['days' => 2]))->assertSessionHasErrors('start_at');
});

it('applies the return buffer in both directions when filtering the catalog', function () {
    $before = Vehicle::factory()->create(['model' => 'Sebelum']);
    $after = Vehicle::factory()->create(['model' => 'Sesudah']);
    holdVehicle($before, OrderStatus::Booking, '2026-10-10 08:00', '2026-10-14 09:00');
    holdVehicle($after, OrderStatus::Booking, '2026-10-16 15:00', '2026-10-17 08:00');

    $models = fn (string $start, int $days) => $this->get(route('vehicles.index', ['start_at' => $start, 'days' => $days, 'search' => 'se']))
        ->viewData('vehicles')->pluck('model')->sort()->values()->all();

    expect($models('2026-10-14T11:59', 1))->toBe(['Sesudah'])
        ->and($models('2026-10-14T12:00', 2))->toBe(['Sebelum', 'Sesudah'])
        ->and($models('2026-10-14T12:01', 2))->toBe(['Sebelum']);
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
        ->assertDontSee('B 1111 ZZ');
});

it('limits the duration options to the vehicle rental limit', function () {
    $vehicle = Vehicle::factory()->create(['max_rental_days' => 3]);

    $this->actingAs(User::factory()->create())
        ->get(route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => '2026-10-14T09:00']))
        ->assertViewHas('options', fn ($options) => $options->pluck('days')->all() === [1, 2, 3]);
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

it('prefills the start time and offers duration options with their return time', function () {
    $vehicle = Vehicle::factory()->create(['price_per_day' => 100000]);

    $this->actingAs(User::factory()->create())
        ->get(route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => '2026-10-14T09:00', 'days' => 2]))
        ->assertSee('value="2026-10-14T09:00"', false)
        ->assertSee('Mulai (WIB)')
        ->assertSee('1 hari &ndash; ', false)
        ->assertSee('15/10/2026 09:00 WIB')
        ->assertSee('2 hari &ndash; ', false)
        ->assertSee('16/10/2026 09:00 WIB')
        ->assertSee('7 hari &ndash; ', false)
        ->assertSee('<option value="2" data-subtotal="200000" selected>', false);
});

it('only offers durations that end before the next booking including the return buffer', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-20 12:00', '2026-10-21 12:00');

    $options = fn (string $start) => $this->actingAs(User::factory()->create())
        ->get(route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => $start]))
        ->assertOk()
        ->viewData('options')
        ->pluck('days')
        ->all();

    expect($options('2026-10-17T09:00'))->toBe([1, 2, 3])
        ->and($options('2026-10-17T09:01'))->toBe([1, 2]);
});

it('explains when the chosen start time itself is not available', function () {
    $vehicle = Vehicle::factory()->create();
    holdVehicle($vehicle, OrderStatus::Booking, '2026-10-14 08:00', '2026-10-15 08:00');

    $this->actingAs(User::factory()->create())
        ->get(route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => '2026-10-14T09:00']))
        ->assertSee('Tidak tersedia pada waktu mulai ini.')
        ->assertViewHas('options', fn ($options) => $options->isEmpty());
});

it('does not show the hourly rate or the rental duration range to customers', function () {
    $vehicle = Vehicle::factory()->create(['price_per_hour' => 12345]);

    $this->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertDontSee('Tarif per jam')
        ->assertDontSee('12.345')
        ->assertDontSee('Durasi sewa');
});

it('links the support button to the company whatsapp number', function () {
    CompanyProfile::factory()->create(['support_whatsapp' => '6281200000000']);
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);

    $this->get(route('vehicles.show', $vehicle))
        ->assertSee('Tanya via WhatsApp')
        ->assertSee('https://wa.me/6281200000000?text=Halo%2C%20saya%20ingin%20bertanya%20tentang%20Honda%20Genio.', false);
});

it('escapes vehicle text on public pages', function () {
    $vehicle = Vehicle::factory()->create(['brand' => '<b>Merek</b>', 'description' => '<script>alert(1)</script>']);

    $this->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<b>Merek</b>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

    $this->get(route('vehicles.index'))->assertDontSee('<b>Merek</b>', false);
});

it('reads the company profile once per page', function () {
    CompanyProfile::factory()->create();
    $vehicle = Vehicle::factory()->create();

    DB::enableQueryLog();
    $this->get(route('vehicles.show', $vehicle))->assertOk();

    $companyQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'company_profiles'));

    expect($companyQueries)->toHaveCount(1);
});

it('never creates a second company profile and always returns the first one', function () {
    $first = CompanyProfile::factory()->create(['name' => 'Pertama']);
    CompanyProfile::factory()->create(['name' => 'Kedua']);

    expect((new CompanyProfileResolver)->get()->is($first))->toBeTrue();

    CompanyProfile::query()->delete();

    $created = (new CompanyProfileResolver)->get();
    $again = (new CompanyProfileResolver)->get();

    expect(CompanyProfile::count())->toBe(1)->and($again->is($created))->toBeTrue();
});

it('shows the energy capacity as a number in the catalog api', function () {
    $vehicle = Vehicle::factory()->create(['drive_type' => 'automatic', 'energy_capacity' => 4.2]);

    $this->getJson(route('api.v1.vehicles.show', $vehicle))->assertJsonPath('data.energy_capacity', 4.2);
});
