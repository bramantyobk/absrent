<?php

use App\Models\User;
use App\Models\Vehicle;

it('requires a staff role to set the rental limit', function () {
    $vehicle = Vehicle::factory()->create();

    $this->put(route('dashboard.vehicles.rental-limit.update', $vehicle), ['max_rental_days' => 3])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())
        ->put(route('dashboard.vehicles.rental-limit.update', $vehicle), ['max_rental_days' => 3])
        ->assertForbidden();
});

it('lets operators and admins set the limit and reset it to the default', function (string $role) {
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->{$role}()->create());

    $this->put(route('dashboard.vehicles.rental-limit.update', $vehicle), ['max_rental_days' => '3'])
        ->assertRedirect(route('dashboard.vehicles.show', $vehicle))
        ->assertSessionHasNoErrors();
    expect($vehicle->refresh()->max_rental_days)->toBe(3)->and($vehicle->effectiveMaxRentalDays())->toBe(3);

    $this->put(route('dashboard.vehicles.rental-limit.update', $vehicle), ['max_rental_days' => ''])->assertSessionHasNoErrors();
    expect($vehicle->refresh()->max_rental_days)->toBeNull()->and($vehicle->effectiveMaxRentalDays())->toBe(7);
})->with(['operator', 'admin']);

it('validates the rental limit', function (mixed $value) {
    $vehicle = Vehicle::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.vehicles.rental-limit.update', $vehicle), ['max_rental_days' => $value])
        ->assertSessionHasErrors('max_rental_days', errorBag: 'rental_limit');
})->with(['zero' => '0', 'too many' => '31', 'text' => 'tujuh']);

it('shows the effective limit on the dashboard vehicle detail', function () {
    $vehicle = Vehicle::factory()->create(['max_rental_days' => 5]);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.vehicles.show', $vehicle))
        ->assertSee('Hari sewa maksimal')
        ->assertSee('5 hari')
        ->assertSee('khusus unit ini');
});

it('sets the limit through the api', function () {
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->operator()->create());

    $this->putJson(route('api.v1.dashboard.vehicles.rental-limit.update', $vehicle), ['max_rental_days' => 4])
        ->assertOk()
        ->assertJsonPath('data.max_rental_days', 4)
        ->assertJsonPath('data.effective_max_rental_days', 4);

    $this->putJson(route('api.v1.dashboard.vehicles.rental-limit.update', $vehicle), ['max_rental_days' => 99])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_rental_days');
});
