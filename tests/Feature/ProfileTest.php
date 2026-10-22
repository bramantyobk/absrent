<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function profilePayload(array $overrides = []): array
{
    return $overrides + ['name' => 'Budi Santoso', 'phone' => '081234567890'];
}

it('requires a customer account for the profile endpoints', function (string $method, string $route) {
    $this->{$method}(route($route))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->{$method}(route($route))->assertForbidden();
})->with([
    ['get', 'profile.edit'],
    ['put', 'profile.update'],
    ['post', 'profile.photo.store'],
    ['delete', 'profile.photo.destroy'],
    ['put', 'profile.password.update'],
]);

it('shows the profile page with the email read-only and initials without a photo', function () {
    $customer = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);

    $this->actingAs($customer)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Profil saya')
        ->assertSee('budi@example.com')
        ->assertSee('>BS<', false)
        ->assertDontSee('Hapus foto')
        ->assertSee('data-photo-input', false);
});

it('shows the profile link and avatar in the customer navbar only', function () {
    $this->actingAs(User::factory()->create(['name' => 'Budi Santoso']))
        ->get(route('home'))
        ->assertSee(route('profile.edit'), false);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('home'))
        ->assertDontSee(route('profile.edit'), false);
});

it('updates the name and normalizes the phone number but never the email', function () {
    $customer = User::factory()->create(['email' => 'tetap@example.com']);

    $this->actingAs($customer)
        ->put(route('profile.update'), profilePayload(['email' => 'baru@example.com']))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    expect($customer->refresh())->name->toBe('Budi Santoso')->phone->toBe('6281234567890')->email->toBe('tetap@example.com');
});

it('clears the duplicate phone flag only when the phone number changes', function () {
    $customer = User::factory()->create(['phone' => '6281234567890', 'phone_flagged_at' => now()]);
    $this->actingAs($customer);

    $this->put(route('profile.update'), profilePayload(['name' => 'Nama Baru']));
    expect($customer->refresh()->phone_flagged_at)->not->toBeNull();

    $this->put(route('profile.update'), profilePayload(['phone' => '081200000001']));
    expect($customer->refresh()->phone_flagged_at)->toBeNull();
});

it('rejects an invalid name or phone number in the profile error bag', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->create())
        ->from(route('profile.edit'))
        ->put(route('profile.update'), profilePayload($overrides))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrorsIn('profile', $field);
})->with([
    'empty name' => [['name' => ''], 'name'],
    'bad phone' => [['phone' => '123'], 'phone'],
]);

it('uploads, replaces, and removes the profile photo and deletes the old file', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer);

    $this->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    $first = $customer->refresh()->photo_path;
    expect($first)->toStartWith('profiles/');
    Storage::disk('public')->assertExists($first);
    $this->get(route('profile.edit'))->assertSee($customer->photo_url, false)->assertSee('Hapus foto');

    $this->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('b.png', 300, 300)]);

    $second = $customer->refresh()->photo_path;
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $this->delete(route('profile.photo.destroy'))->assertRedirect(route('profile.edit'));

    expect($customer->refresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

it('rejects a missing, oversized, or non-image photo', function (callable $file, string $message) {
    $this->actingAs(User::factory()->create())
        ->from(route('profile.edit'))
        ->post(route('profile.photo.store'), ['photo' => $file()])
        ->assertSessionHasErrorsIn('photo', ['photo' => $message]);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'missing' => [fn () => null, 'Pilih foto terlebih dahulu.'],
    'too large' => [fn () => UploadedFile::fake()->image('big.jpg')->size(2048), 'Ukuran foto maksimal 1 MB.'],
    'not an image' => [fn () => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'File harus berupa gambar.'],
]);

it('changes the password only with the correct current password', function () {
    $customer = User::factory()->create(['password' => 'password-lama-1']);
    $this->actingAs($customer)->from(route('profile.edit'));

    $this->put(route('profile.password.update'), ['current_password' => 'salah', 'password' => 'Password-Baru-9', 'password_confirmation' => 'Password-Baru-9'])
        ->assertSessionHasErrorsIn('password', ['current_password' => 'Password lama tidak sesuai.']);

    expect(Hash::check('password-lama-1', $customer->refresh()->password))->toBeTrue();

    $this->put(route('profile.password.update'), ['current_password' => 'password-lama-1', 'password' => 'Password-Baru-9', 'password_confirmation' => 'Password-Baru-9'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('Password-Baru-9', $customer->refresh()->password))->toBeTrue();
});

it('rejects a weak, unconfirmed, or unchanged new password', function (array $payload, string $field) {
    $customer = User::factory()->create(['password' => 'password-lama-1']);

    $this->actingAs($customer)
        ->from(route('profile.edit'))
        ->put(route('profile.password.update'), $payload + ['current_password' => 'password-lama-1'])
        ->assertSessionHasErrorsIn('password', $field);

    expect(Hash::check('password-lama-1', $customer->refresh()->password))->toBeTrue();
})->with([
    'too short' => [['password' => 'a1', 'password_confirmation' => 'a1'], 'password'],
    'not confirmed' => [['password' => 'Password-Baru-9', 'password_confirmation' => 'lain'], 'password'],
    'same as current' => [['password' => 'password-lama-1', 'password_confirmation' => 'password-lama-1'], 'password'],
]);

it('removes the profile photo file when an admin deletes the customer', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('a.jpg')]);
    $path = $customer->refresh()->photo_path;

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('dashboard.users.destroy', $customer))
        ->assertRedirect(route('dashboard.users.index'));

    Storage::disk('public')->assertMissing($path);
});
