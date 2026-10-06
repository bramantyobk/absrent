<?php

use App\Models\User;

it('redirects guests from the dashboard to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('forbids customers from the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('allows admin and operator into the dashboard', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('dashboard'))
        ->assertOk();
})->with(['admin', 'operator']);

it('forbids deactivated staff from the dashboard', function () {
    $this->actingAs(User::factory()->operator()->inactive()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});
