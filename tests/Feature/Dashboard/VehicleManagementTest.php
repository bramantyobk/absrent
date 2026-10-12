<?php

use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\VehicleType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function validVehicleData(array $overrides = []): array
{
    return $overrides + [
        'brand' => 'Honda',
        'model' => 'Vario 125',
        'plate_number' => 'b  1234   xyz',
        'type' => 'motorcycle',
        'year' => '2023',
        'color' => 'Hitam',
        'price_per_day' => '85000',
        'price_per_hour' => '9000',
        'is_available' => '1',
        'drive_type' => 'automatic',
        'energy_capacity' => '5.5',
    ];
}

it('requires a staff role for every vehicle action', function (string $method, string $route) {
    $vehicle = Vehicle::factory()->create();

    $this->{$method}(route($route, $vehicle))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $vehicle))
        ->assertForbidden();
})->with([
    ['get', 'dashboard.vehicles.index'],
    ['get', 'dashboard.vehicles.create'],
    ['get', 'dashboard.vehicles.edit'],
    ['post', 'dashboard.vehicles.store'],
    ['put', 'dashboard.vehicles.update'],
    ['delete', 'dashboard.vehicles.destroy'],
]);

it('lists vehicles with search, type, and availability filters and paginates by ten', function () {
    Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'model' => 'NMAX', 'plate_number' => 'B 1111 AA']);
    Vehicle::factory()->car()->create(['brand' => 'Toyota', 'model' => 'Avanza', 'plate_number' => 'B 2222 BB']);
    Vehicle::factory()->car()->unavailable()->create(['brand' => 'Honda', 'model' => 'Brio', 'plate_number' => 'B 3333 CC']);

    $this->actingAs(User::factory()->operator()->create());

    $models = fn (array $query) => $this->get(route('dashboard.vehicles.index', $query))
        ->assertOk()
        ->viewData('vehicles')
        ->pluck('model')
        ->all();

    expect($models([]))->toHaveCount(3)
        ->and($models(['search' => 'nmax']))->toBe(['NMAX'])
        ->and($models(['search' => '2222']))->toBe(['Avanza'])
        ->and($models(['type' => 'car']))->toHaveCount(2)
        ->and($models(['status' => 'unavailable']))->toBe(['Brio']);

    Vehicle::factory()->count(10)->create();

    expect($this->get(route('dashboard.vehicles.index'))->viewData('vehicles')->items())->toHaveCount(10);
});

it('rejects invalid list filters', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.vehicles.index', ['type' => 'boat', 'status' => 'maybe']))
        ->assertSessionHasErrors(['type', 'status']);
});

it('creates a vehicle with a normalized plate and a resized jpeg photo', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->operator()->create());

    $this->post(route('dashboard.vehicles.store'), validVehicleData([
        'photo' => UploadedFile::fake()->image('motor.png', 2400, 1600),
    ]))->assertRedirect(route('dashboard.vehicles.index'))->assertSessionHas('status');

    $vehicle = Vehicle::firstOrFail();

    expect($vehicle->plate_number)->toBe('B 1234 XYZ')
        ->and($vehicle->type)->toBe(VehicleType::Motorcycle)
        ->and($vehicle->photo)->toStartWith('vehicles/')->toEndWith('.jpg');

    Storage::disk('public')->assertExists($vehicle->photo);
    expect(getimagesizefromstring(Storage::disk('public')->get($vehicle->photo)))
        ->toMatchArray([0 => 1200, 1 => 800]);
});

it('creates a vehicle without a photo and falls back to the placeholder', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.vehicles.store'), validVehicleData(['price_per_hour' => '']));

    $vehicle = Vehicle::firstOrFail();

    expect($vehicle->photo)->toBeNull()
        ->and($vehicle->price_per_hour)->toBeNull()
        ->and($vehicle->photo_url)->toContain('images/vehicle-placeholder.png');
});

it('validates vehicle input', function (array $overrides, string $field) {
    Vehicle::factory()->create(['plate_number' => 'B 9999 ZZ']);
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.vehicles.store'), validVehicleData($overrides))->assertSessionHasErrors($field);

    expect(Vehicle::count())->toBe(1);
})->with([
    'duplicate plate' => [['plate_number' => 'b 9999 zz'], 'plate_number'],
    'missing brand' => [['brand' => ''], 'brand'],
    'unknown type' => [['type' => 'boat'], 'type'],
    'year too old' => [['year' => '1900'], 'year'],
    'zero daily price' => [['price_per_day' => '0'], 'price_per_day'],
    'non-image photo' => [['photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], 'photo'],
    'oversized photo' => [['photo' => UploadedFile::fake()->image('big.jpg')->size(1500)], 'photo'],
]);

it('reports an unreadable image instead of failing with a server error', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.vehicles.store'), validVehicleData([
        'photo' => UploadedFile::fake()->createWithContent('rusak.jpg', 'bukan gambar'),
    ]))->assertSessionHasErrors('photo');

    expect(Vehicle::count())->toBe(0);
});

it('removes the new photo when saving the vehicle fails', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    Vehicle::creating(fn () => throw new RuntimeException('gagal simpan'));
    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('dashboard.vehicles.store'), validVehicleData([
        'photo' => UploadedFile::fake()->image('motor.jpg'),
    ])))->toThrow(RuntimeException::class);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('requires a drive type and energy capacity but keeps the description optional', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.vehicles.store'), validVehicleData($overrides))
        ->assertSessionHasErrors($field);

    expect(Vehicle::count())->toBe(0);
})->with([
    'missing drive type' => [['drive_type' => ''], 'drive_type'],
    'unknown drive type' => [['drive_type' => 'hybrid'], 'drive_type'],
    'missing capacity' => [['energy_capacity' => ''], 'energy_capacity'],
    'zero capacity' => [['energy_capacity' => '0'], 'energy_capacity'],
    'too many decimals' => [['energy_capacity' => '5.55'], 'energy_capacity'],
]);

it('stores the new vehicle details and the initial odometer on create only', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.vehicles.store'), validVehicleData([
        'drive_type' => 'electric', 'energy_capacity' => '3.2', 'description' => 'Motor listrik', 'odometer_km' => '120',
    ]))->assertSessionHasNoErrors();

    $vehicle = Vehicle::sole();

    expect($vehicle->drive_type->value)->toBe('electric')
        ->and($vehicle->energyCapacityLabel())->toBe('3,2 kWh')
        ->and($vehicle->description)->toBe('Motor listrik')
        ->and($vehicle->odometer_km)->toBe(120);

    $this->put(route('dashboard.vehicles.update', $vehicle), validVehicleData(['odometer_km' => '99999']))->assertSessionHasNoErrors();

    expect($vehicle->refresh()->odometer_km)->toBe(120);
});

it('renders the edit page', function () {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.vehicles.edit', $vehicle))
        ->assertOk()
        ->assertSee($vehicle->plate_number);
});

it('updates a vehicle, replaces its photo, and keeps its own plate', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $vehicle = Vehicle::factory()->create(['plate_number' => 'B 1234 XYZ', 'photo' => 'vehicles/lama.jpg']);
    Storage::disk('public')->put('vehicles/lama.jpg', 'x');

    $this->put(route('dashboard.vehicles.update', $vehicle), validVehicleData([
        'color' => 'Merah',
        'photo' => UploadedFile::fake()->image('baru.jpg'),
    ]))->assertRedirect(route('dashboard.vehicles.index'))->assertSessionHasNoErrors();

    $vehicle->refresh();

    expect($vehicle->color)->toBe('Merah')
        ->and($vehicle->photo)->not->toBe('vehicles/lama.jpg');
    Storage::disk('public')->assertMissing('vehicles/lama.jpg');
    Storage::disk('public')->assertExists($vehicle->photo);
});

it('keeps the photo on update without a new file and removes it on request', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $vehicle = Vehicle::factory()->create(['plate_number' => 'B 1234 XYZ', 'photo' => 'vehicles/foto.jpg']);
    Storage::disk('public')->put('vehicles/foto.jpg', 'x');

    $this->put(route('dashboard.vehicles.update', $vehicle), validVehicleData());
    expect($vehicle->refresh()->photo)->toBe('vehicles/foto.jpg');

    $this->put(route('dashboard.vehicles.update', $vehicle), validVehicleData(['remove_photo' => '1']));
    expect($vehicle->refresh()->photo)->toBeNull();
    Storage::disk('public')->assertMissing('vehicles/foto.jpg');
});

it('rejects a plate that belongs to another vehicle on update', function () {
    Vehicle::factory()->create(['plate_number' => 'B 9999 ZZ']);
    $vehicle = Vehicle::factory()->create(['plate_number' => 'B 1234 XYZ']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.vehicles.update', $vehicle), validVehicleData(['plate_number' => 'B 9999 ZZ']))
        ->assertSessionHasErrors('plate_number');
});

it('deletes a vehicle together with its photo', function () {
    Storage::fake('public');
    $vehicle = Vehicle::factory()->create(['photo' => 'vehicles/foto.jpg']);
    Storage::disk('public')->put('vehicles/foto.jpg', 'x');

    $this->actingAs(User::factory()->operator()->create())
        ->delete(route('dashboard.vehicles.destroy', $vehicle))
        ->assertRedirect(route('dashboard.vehicles.index'))
        ->assertSessionHas('status');

    expect(Vehicle::count())->toBe(0);
    Storage::disk('public')->assertMissing('vehicles/foto.jpg');
});

it('refuses to delete a vehicle that has order history', function () {
    $vehicle = Vehicle::factory()->create();
    OrderItem::factory()->for($vehicle)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('dashboard.vehicles.destroy', $vehicle))
        ->assertSessionHas('error');

    expect($vehicle->exists())->toBeTrue()->and(Vehicle::count())->toBe(1);
});
