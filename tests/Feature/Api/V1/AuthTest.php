<?php

use App\Models\User;
use App\UserRole;
use Laravel\Sanctum\PersonalAccessToken;

function registrationPayload(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Budi Pelanggan',
        'email' => 'budi@example.com',
        'phone' => '0812-3456-7890',
        'password' => 'password-rahasia',
        'password_confirmation' => 'password-rahasia',
        'device_name' => 'Pixel 8',
    ];
}

it('registers a customer with a normalized phone number and returns a bearer token', function () {
    $this->postJson(route('api.v1.auth.register'), registrationPayload(['role' => 'admin']))
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.role', 'user')
        ->assertJsonPath('data.user.phone', '6281234567890')
        ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'name', 'email', 'phone', 'role', 'role_label', 'is_active']]]);

    $user = User::where('email', 'budi@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::User)
        ->and($user->tokens()->pluck('name')->all())->toBe(['Pixel 8']);
});

it('validates registration input', function (array $overrides, string $field) {
    User::factory()->create(['email' => 'dipakai@example.com']);

    $this->postJson(route('api.v1.auth.register'), registrationPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(User::where('name', 'Budi Pelanggan')->exists())->toBeFalse();
})->with([
    'missing device name' => [['device_name' => ''], 'device_name'],
    'duplicate email' => [['email' => 'dipakai@example.com'], 'email'],
    'invalid phone' => [['phone' => '123'], 'phone'],
    'password mismatch' => [['password_confirmation' => 'beda'], 'password'],
]);

it('issues a token on login that authenticates later requests', function () {
    $user = User::factory()->create(['password' => 'password-rahasia']);

    $token = $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'password-rahasia',
        'device_name' => 'Pixel 8',
    ])->assertOk()->json('data.token');

    $this->withToken($token)
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('rejects wrong credentials and inactive accounts with the same message', function (array $state, string $password) {
    $user = User::factory()->create(['password' => 'password-rahasia', ...$state]);

    $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'Pixel 8',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Email atau password salah, atau akun tidak aktif.']);

    expect($user->tokens()->count())->toBe(0);
})->with([
    'wrong password' => [[], 'salah'],
    'inactive account' => [['is_active' => false], 'password-rahasia'],
]);

it('throttles repeated failed logins', function () {
    $payload = ['email' => 'tidak@ada.test', 'password' => 'salah', 'device_name' => 'Pixel 8'];

    foreach (range(1, 6) as $attempt) {
        $this->postJson(route('api.v1.auth.login'), $payload)->assertUnprocessable();
    }

    $this->postJson(route('api.v1.auth.login'), $payload)->assertTooManyRequests();
});

it('requires authentication for the profile and logout endpoints', function (string $method, string $route) {
    $this->{$method}(route($route))->assertUnauthorized();
})->with([
    ['getJson', 'api.v1.auth.me'],
    ['postJson', 'api.v1.auth.logout'],
]);

it('accepts the web session cookie as well as a bearer token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('revokes only the token used to log out', function () {
    $user = User::factory()->create();
    $current = $user->createToken('Pixel 8');
    $other = $user->createToken('iPad');

    $this->withToken($current->plainTextToken)
        ->postJson(route('api.v1.auth.logout'))
        ->assertNoContent();

    expect(PersonalAccessToken::find($current->accessToken->id))->toBeNull()
        ->and(PersonalAccessToken::find($other->accessToken->id))->not->toBeNull();
});

it('refuses tokens that belong to a deactivated account', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel 8')->plainTextToken;

    $user->update(['is_active' => false]);

    $this->withToken($token)->getJson(route('api.v1.auth.me'))->assertForbidden();
});

it('expires tokens after the configured lifetime', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel 8')->plainTextToken;

    $this->travel(config('sanctum.expiration') + 1)->minutes();

    $this->withToken($token)->getJson(route('api.v1.auth.me'))->assertUnauthorized();
});
