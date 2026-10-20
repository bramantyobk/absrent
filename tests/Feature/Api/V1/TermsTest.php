<?php

use App\Models\CompanyProfile;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;

beforeEach(function () {
    $this->travelTo('2026-10-12 10:00:00');
});

it('rejects guests and staff on the customer terms endpoint', function () {
    $this->getJson(route('api.v1.terms.show'))->assertUnauthorized();
    $this->actingAs(User::factory()->operator()->create())->getJson(route('api.v1.terms.show'))->assertForbidden();
});

it('returns the filled terms as text and blocks', function () {
    CompanyProfile::factory()->create(['terms_template' => "# Judul\nPenyewa {name}\n{vehicles}\nTotal {total_price}"]);
    $first = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Genio', 'plate_number' => 'DK 1 AB', 'price_per_day' => 75000]);
    $second = Vehicle::factory()->car()->create(['brand' => 'Toyota', 'model' => 'Avanza', 'plate_number' => 'DK 2 CD', 'price_per_day' => 350000]);

    $this->actingAs(User::factory()->create(['name' => 'Budi']))
        ->getJson(route('api.v1.terms.show', ['start_at' => '2026-10-14T09:00:00+07:00', 'days' => 2, 'vehicle_ids' => [$second->id, $first->id]]))
        ->assertOk()
        ->assertJsonPath('data.blocks.0', ['type' => 'heading', 'text' => 'Judul'])
        ->assertJsonPath('data.blocks.1.type', 'paragraph')
        ->assertJsonPath('data.text', "# Judul\nPenyewa Budi\n1. Toyota Avanza (DK 2 CD)\n2. Honda Genio (DK 1 AB)\nTotal Rp850.000");

    expect(Order::count())->toBe(0);
});

it('validates the preview period and vehicles', function () {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('api.v1.terms.show'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_at', 'days', 'vehicle_ids']);

    $this->getJson(route('api.v1.terms.show', ['start_at' => '2026-10-01T09:00:00+07:00', 'days' => 2, 'vehicle_ids' => [$vehicle->id]]))
        ->assertUnprocessable();
});

it('requires terms acceptance to create an order through the api', function () {
    $vehicle = Vehicle::factory()->create();
    $payload = ['start_at' => '2026-10-14T09:00:00+07:00', 'days' => 2, 'items' => [['vehicle_id' => $vehicle->id]]];
    $this->actingAs(User::factory()->create());

    $this->postJson(route('api.v1.checkout.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('terms_accepted');
    $this->postJson(route('api.v1.checkout.store'), $payload + ['terms_accepted' => false])->assertUnprocessable()->assertJsonValidationErrors('terms_accepted');

    expect(Order::count())->toBe(0);

    $this->postJson(route('api.v1.checkout.store'), $payload + ['terms_accepted' => true])->assertCreated();
});
