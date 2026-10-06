<?php

use App\Models\User;
use App\UserRole;

it('shows the register page to guests', function () {
    $this->get(route('register'))->assertOk()->assertSee('Daftar');
});

it('registers a customer with the user role and normalizes the phone number', function () {
    $this->post(route('register'), [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'phone' => '0812-3456-7890',
        'password' => 'password-rahasia',
        'password_confirmation' => 'password-rahasia',
    ])->assertRedirect(route('home'));

    $user = User::where('email', 'budi@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::User)
        ->and($user->phone)->toBe('6281234567890')
        ->and($user->is_active)->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('does not let a registrant choose their own role', function () {
    $this->post(route('register'), [
        'name' => 'Nakal',
        'email' => 'nakal@example.com',
        'phone' => '081234567890',
        'password' => 'password-rahasia',
        'password_confirmation' => 'password-rahasia',
        'role' => 'admin',
    ]);

    expect(User::where('email', 'nakal@example.com')->firstOrFail()->role)->toBe(UserRole::User);
});

it('validates registration input', function (array $overrides, string $field) {
    User::factory()->create(['email' => 'dipakai@example.com']);

    $this->post(route('register'), $overrides + [
        'name' => 'Budi',
        'email' => 'budi@example.com',
        'phone' => '081234567890',
        'password' => 'password-rahasia',
        'password_confirmation' => 'password-rahasia',
    ])->assertSessionHasErrors($field);

    $this->assertGuest();
})->with([
    'email already taken' => [['email' => 'dipakai@example.com'], 'email'],
    'invalid phone' => [['phone' => '123'], 'phone'],
    'password mismatch' => [['password_confirmation' => 'beda'], 'password'],
    'missing name' => [['name' => ''], 'name'],
]);
