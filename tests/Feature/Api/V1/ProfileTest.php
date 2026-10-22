<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('rejects guests and staff on the profile endpoints', function (string $method, string $route) {
    $this->{$method}(route($route))->assertUnauthorized();
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route))->assertForbidden();
})->with([
    ['putJson', 'api.v1.profile.update'],
    ['postJson', 'api.v1.profile.photo.update'],
    ['deleteJson', 'api.v1.profile.photo.destroy'],
    ['putJson', 'api.v1.profile.password.update'],
]);

it('updates the profile through the api without touching the email', function () {
    $customer = User::factory()->create(['email' => 'tetap@example.com']);

    $this->actingAs($customer)
        ->putJson(route('api.v1.profile.update'), ['name' => 'Budi', 'phone' => '081234567890', 'email' => 'lain@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Budi')
        ->assertJsonPath('data.phone', '6281234567890')
        ->assertJsonPath('data.email', 'tetap@example.com');

    $this->putJson(route('api.v1.profile.update'), ['name' => '', 'phone' => '1'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);
});

it('uploads and removes the profile photo through the api', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer);

    $response = $this->postJson(route('api.v1.profile.photo.update'), ['photo' => UploadedFile::fake()->image('a.jpg', 500, 500)])
        ->assertOk();

    expect($response->json('data.photo_url'))->toContain('/profiles/');
    Storage::disk('public')->assertExists($customer->refresh()->photo_path);

    $this->postJson(route('api.v1.profile.photo.update'), [])->assertUnprocessable()->assertJsonValidationErrors('photo');

    $this->deleteJson(route('api.v1.profile.photo.destroy'))->assertOk()->assertJsonPath('data.photo_url', null);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('changes the password through the api and revokes the other tokens', function () {
    $customer = User::factory()->create(['password' => 'password-lama-1']);
    $customer->createToken('lain');
    $current = $customer->createToken('saat-ini')->plainTextToken;

    $payload = ['current_password' => 'password-lama-1', 'password' => 'Password-Baru-9', 'password_confirmation' => 'Password-Baru-9'];

    $this->withToken($current)->putJson(route('api.v1.profile.password.update'), ['current_password' => 'salah'] + $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->withToken($current)->putJson(route('api.v1.profile.password.update'), $payload)->assertNoContent();

    expect(Hash::check('Password-Baru-9', $customer->refresh()->password))->toBeTrue()
        ->and($customer->tokens()->pluck('name')->all())->toBe(['saat-ini']);
});
