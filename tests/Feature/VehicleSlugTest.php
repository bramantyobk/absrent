<?php

use App\Models\User;
use App\Models\Vehicle;

it('builds the slug from brand and model and adds a number suffix when it is taken', function () {
    $first = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Avanza']);
    $second = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Avanza']);
    $third = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Avanza']);
    $other = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Vario 125']);

    expect($first->slug)->toBe('toyota-avanza')
        ->and($second->slug)->toBe('toyota-avanza-2')
        ->and($third->slug)->toBe('toyota-avanza-3')
        ->and($other->slug)->toBe('honda-vario-125');
});

it('changes the slug when the brand or model changes and keeps it otherwise', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Avanza']);
    $duplicate = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Avanza']);

    $duplicate->update(['color' => 'Hitam']);
    expect($duplicate->fresh()->slug)->toBe('toyota-avanza-2');

    $vehicle->update(['model' => 'Veloz']);
    expect($vehicle->fresh()->slug)->toBe('toyota-veloz');

    $duplicate->update(['model' => 'Veloz']);
    expect($duplicate->fresh()->slug)->toBe('toyota-veloz-2');

    $duplicate->update(['model' => 'Avanza']);
    expect($duplicate->fresh()->slug)->toBe('toyota-avanza');
});

it('opens the public vehicle page by slug and answers 404 for the id or an old slug', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $oldSlug = $vehicle->slug;

    $this->get('/vehicles/honda-genio')->assertOk()->assertSee('Honda Genio');
    $this->get(route('vehicles.show', $vehicle))->assertOk();
    expect(route('vehicles.show', $vehicle))->toEndWith('/vehicles/honda-genio');

    $this->get("/vehicles/{$vehicle->id}")->assertNotFound();

    $vehicle->update(['model' => 'Scoopy']);

    $this->get("/vehicles/{$oldSlug}")->assertNotFound();
    $this->get('/vehicles/honda-scoopy')->assertOk();
});

it('links catalog cards to the slug url and keeps hidden vehicles at 404', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $hidden = Vehicle::factory()->unavailable()->create(['brand' => 'Honda', 'model' => 'Vario']);

    $this->get(route('vehicles.index'))->assertOk()->assertSee('/vehicles/honda-genio', false);
    $this->get(route('vehicles.show', $hidden))->assertNotFound();
});

it('uses the slug in the public api and keeps the id in the dashboard', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);

    $this->getJson('/api/v1/vehicles/honda-genio')->assertOk()->assertJsonPath('data.slug', 'honda-genio')->assertJsonPath('data.id', $vehicle->id);
    $this->getJson("/api/v1/vehicles/{$vehicle->id}")->assertNotFound();
    $this->getJson('/api/v1/vehicles/honda-genio/return-options?start_at='.urlencode(now()->addDay()->toIso8601String()))->assertOk();
    $this->getJson('/api/v1/vehicles/honda-genio/quote?start_at='.urlencode(now()->addDay()->toIso8601String()).'&days=1')->assertOk();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('api.v1.dashboard.vehicles.show', $vehicle))
        ->assertOk()
        ->assertJsonPath('data.slug', 'honda-genio');
    $this->get(route('dashboard.vehicles.show', $vehicle))->assertOk();
});

it('lets the customer pick a schedule through the slug url and keeps the cart by id', function () {
    $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio']);
    $this->actingAs(User::factory()->create());

    $this->get('/vehicles/honda-genio')
        ->assertOk()
        ->assertSee('/api/v1/vehicles/honda-genio/return-options', false);

    $this->post(route('checkout.items.store', $vehicle), ['start_at' => now()->addDay()->format('Y-m-d\TH:i'), 'days' => 1])->assertSessionHasNoErrors();
    $this->post(route('checkout.reschedule'))->assertRedirectContains('/vehicles/honda-genio');
});
