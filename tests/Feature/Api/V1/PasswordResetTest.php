<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

it('records a reset request through the api with the same answer for every email', function () {
    $customer = User::factory()->create();

    $this->postJson(route('api.v1.auth.forgot-password'), ['email' => $customer->email])->assertOk()->assertJsonPath('message', 'Permintaan diterima. Admin akan mengirim tautan reset ke WhatsApp Anda.');
    $this->postJson(route('api.v1.auth.forgot-password'), ['email' => 'tidak-ada@example.com'])->assertOk()->assertJsonPath('message', 'Permintaan diterima. Admin akan mengirim tautan reset ke WhatsApp Anda.');
    $this->postJson(route('api.v1.auth.forgot-password'), ['email' => User::factory()->operator()->create()->email])->assertOk();
    $this->postJson(route('api.v1.auth.forgot-password'), ['email' => 'bukan-email'])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(User::whereNotNull('password_reset_requested_at')->pluck('id')->all())->toBe([$customer->id]);
});

it('resets the password through the api and revokes existing tokens', function () {
    $customer = User::factory()->create(['password' => 'password-lama-1', 'password_reset_requested_at' => now()]);
    $customer->createToken('lama');
    $token = Password::broker()->createToken($customer);
    $payload = ['token' => $token, 'email' => $customer->email, 'password' => 'Password-Baru-9', 'password_confirmation' => 'Password-Baru-9'];

    $this->postJson(route('api.v1.auth.reset-password'), $payload)->assertOk();

    expect(Hash::check('Password-Baru-9', $customer->refresh()->password))->toBeTrue()
        ->and($customer->password_reset_requested_at)->toBeNull()
        ->and($customer->tokens()->count())->toBe(0);

    $this->postJson(route('api.v1.auth.reset-password'), $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('rejects an expired token through the api', function () {
    $customer = User::factory()->create();
    $token = Password::broker()->createToken($customer);

    $this->travel(11)->minutes();

    $this->postJson(route('api.v1.auth.reset-password'), ['token' => $token, 'email' => $customer->email, 'password' => 'Password-Baru-9', 'password_confirmation' => 'Password-Baru-9'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('lets only an admin issue a reset link through the api', function () {
    $customer = User::factory()->create(['phone' => '6281234567890', 'password_reset_requested_at' => now()]);

    $this->postJson(route('api.v1.dashboard.users.password-reset-link', $customer))->assertUnauthorized();
    $this->actingAs($customer)->postJson(route('api.v1.dashboard.users.password-reset-link', $customer))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->postJson(route('api.v1.dashboard.users.password-reset-link', $customer))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('api.v1.dashboard.users.password-reset-link', User::factory()->operator()->create()))
        ->assertForbidden();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('api.v1.dashboard.users.password-reset-link', $customer))
        ->assertOk()
        ->assertJsonPath('data.expires_in_minutes', 10);

    expect($response->json('data.url'))->toContain('/reset-password/')
        ->and($response->json('data.whatsapp_url'))->toStartWith('https://wa.me/6281234567890?text=')
        ->and($customer->refresh()->password_reset_requested_at)->toBeNull();
});
