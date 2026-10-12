<?php

use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function vehiclePayload(array $overrides = []): array
{
    return $overrides + [
        'brand' => 'Honda',
        'model' => 'Vario 125',
        'plate_number' => 'b  1234   xyz',
        'type' => 'motorcycle',
        'year' => 2023,
        'color' => 'Hitam',
        'price_per_day' => 85000,
        'price_per_hour' => 9000,
        'is_available' => true,
        'drive_type' => 'automatic',
        'energy_capacity' => 5.5,
    ];
}

it('rejects guests and customers on every vehicle endpoint', function (string $method, string $route, bool $needsVehicle) {
    $parameters = $needsVehicle ? [Vehicle::factory()->create()] : [];

    $this->{$method}(route($route, $parameters))->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $parameters))
        ->assertForbidden();
})->with([
    ['getJson', 'api.v1.dashboard.vehicles.index', false],
    ['postJson', 'api.v1.dashboard.vehicles.store', false],
    ['getJson', 'api.v1.dashboard.vehicles.show', true],
    ['putJson', 'api.v1.dashboard.vehicles.update', true],
    ['deleteJson', 'api.v1.dashboard.vehicles.destroy', true],
]);

it('lists vehicles with filters and pagination for a staff token', function () {
    Vehicle::factory()->motorcycle()->create(['brand' => 'Yamaha', 'model' => 'NMAX', 'plate_number' => 'B 1111 AA']);
    Vehicle::factory()->car()->create(['brand' => 'Toyota', 'model' => 'Avanza', 'plate_number' => 'B 2222 BB']);
    Vehicle::factory()->car()->unavailable()->create(['brand' => 'Honda', 'model' => 'Brio', 'plate_number' => 'B 3333 CC']);
    $token = User::factory()->operator()->create()->createToken('Pixel 8')->plainTextToken;

    $models = fn (array $query) => $this->withToken($token)
        ->getJson(route('api.v1.dashboard.vehicles.index', $query))
        ->assertOk()
        ->json('data.*.model');

    expect($models([]))->toBe(['Brio', 'Avanza', 'NMAX'])
        ->and($models(['search' => '2222']))->toBe(['Avanza'])
        ->and($models(['type' => 'car']))->toBe(['Brio', 'Avanza'])
        ->and($models(['status' => 'unavailable']))->toBe(['Brio']);

    $this->withToken($token)
        ->getJson(route('api.v1.dashboard.vehicles.index', ['per_page' => 2]))
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
});

it('rejects invalid list filters with a json error', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('api.v1.dashboard.vehicles.index', ['type' => 'boat', 'per_page' => 500]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'per_page']);
});

it('shows a vehicle with its photo url', function () {
    $vehicle = Vehicle::factory()->create(['photo' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('api.v1.dashboard.vehicles.show', $vehicle))
        ->assertOk()
        ->assertJsonPath('data.id', $vehicle->id)
        ->assertJsonPath('data.type_label', $vehicle->type->label())
        ->assertJson(fn ($json) => $json->where('data.photo_url', fn (string $url) => str_contains($url, 'images/vehicle-placeholder.png'))->etc());
});

it('creates a vehicle with a resized photo from a multipart request', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->operator()->create());

    $response = $this->post(route('api.v1.dashboard.vehicles.store'), vehiclePayload([
        'photo' => UploadedFile::fake()->image('motor.png', 2400, 1600),
    ]), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.plate_number', 'B 1234 XYZ');

    $vehicle = Vehicle::findOrFail($response->json('data.id'));

    Storage::disk('public')->assertExists($vehicle->photo);
    expect(getimagesizefromstring(Storage::disk('public')->get($vehicle->photo)))->toMatchArray([0 => 1200, 1 => 800]);
});

it('validates vehicle input and returns field errors', function () {
    Vehicle::factory()->create(['plate_number' => 'B 9999 ZZ']);
    $this->actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.dashboard.vehicles.store'), vehiclePayload([
        'plate_number' => 'b 9999 zz',
        'brand' => '',
        'price_per_day' => 0,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['plate_number', 'brand', 'price_per_day']);

    expect(Vehicle::count())->toBe(1);
});

it('updates a vehicle with json and keeps its own plate and photo', function () {
    $vehicle = Vehicle::factory()->create(['plate_number' => 'B 1234 XYZ', 'photo' => 'vehicles/foto.jpg']);

    $this->actingAs(User::factory()->admin()->create())
        ->putJson(route('api.v1.dashboard.vehicles.update', $vehicle), vehiclePayload(['color' => 'Merah']))
        ->assertOk()
        ->assertJsonPath('data.color', 'Merah');

    expect($vehicle->refresh()->photo)->toBe('vehicles/foto.jpg');
});

it('replaces and removes the photo when updating', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $vehicle = Vehicle::factory()->create(['plate_number' => 'B 1234 XYZ', 'photo' => 'vehicles/lama.jpg']);
    Storage::disk('public')->put('vehicles/lama.jpg', 'x');

    $this->post(route('api.v1.dashboard.vehicles.update', $vehicle), vehiclePayload([
        '_method' => 'PUT',
        'photo' => UploadedFile::fake()->image('baru.jpg'),
    ]), ['Accept' => 'application/json'])->assertOk();

    $vehicle->refresh();
    Storage::disk('public')->assertMissing('vehicles/lama.jpg');
    Storage::disk('public')->assertExists($vehicle->photo);

    $this->putJson(route('api.v1.dashboard.vehicles.update', $vehicle), vehiclePayload(['remove_photo' => true]))->assertOk();

    expect($vehicle->refresh()->photo)->toBeNull();
});

it('deletes a vehicle together with its photo', function () {
    Storage::fake('public');
    $vehicle = Vehicle::factory()->create(['photo' => 'vehicles/foto.jpg']);
    Storage::disk('public')->put('vehicles/foto.jpg', 'x');

    $this->actingAs(User::factory()->operator()->create())
        ->deleteJson(route('api.v1.dashboard.vehicles.destroy', $vehicle))
        ->assertNoContent();

    expect(Vehicle::count())->toBe(0);
    Storage::disk('public')->assertMissing('vehicles/foto.jpg');
});

it('answers 409 when deleting a vehicle that has order history', function () {
    $vehicle = Vehicle::factory()->create();
    OrderItem::factory()->for($vehicle)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->deleteJson(route('api.v1.dashboard.vehicles.destroy', $vehicle))
        ->assertConflict()
        ->assertJsonStructure(['message']);

    expect(Vehicle::count())->toBe(1);
});
