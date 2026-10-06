<?php

use App\Models\User;

it('shows the login page to guests', function () {
    $this->get(route('login'))->assertOk()->assertSee('Masuk');
});

it('logs in a user and redirects to home', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('redirects staff to the dashboard after login', function (string $state) {
    $staff = User::factory()->{$state}()->create();

    $this->post(route('login'), ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
})->with(['admin', 'operator']);

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->from(route('login'))
        ->post(route('login'), ['email' => $user->email, 'password' => 'salah'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects an inactive account', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

it('redirects authenticated users away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('home'));
});
