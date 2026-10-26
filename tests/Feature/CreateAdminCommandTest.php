<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Hash;

function adminOptions(array $overrides = []): array
{
    return $overrides + [
        '--name' => 'Admin Utama',
        '--email' => 'admin@example.com',
        '--phone' => '081234567890',
        '--password' => 'Password-Kuat-9',
    ];
}

it('creates an active admin with a normalized phone and a hashed password', function () {
    $this->artisan('app:create-admin', adminOptions())->assertSuccessful();

    $admin = User::sole();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->name)->toBe('Admin Utama')
        ->and($admin->email)->toBe('admin@example.com')
        ->and($admin->phone)->toBe('6281234567890')
        ->and(Hash::check('Password-Kuat-9', $admin->password))->toBeTrue();
});

it('asks for the missing values interactively', function () {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Nama', 'Admin Utama')
        ->expectsQuestion('Email', 'admin@example.com')
        ->expectsQuestion('Nomor WhatsApp', '081234567890')
        ->expectsQuestion('Password', 'Password-Kuat-9')
        ->assertSuccessful();

    expect(User::sole()->role)->toBe(UserRole::Admin);
});

it('refuses invalid or duplicate input and creates nobody', function (array $overrides, string $message) {
    User::factory()->create(['email' => 'dipakai@example.com']);

    $this->artisan('app:create-admin', adminOptions($overrides))
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(User::count())->toBe(1);
})->with([
    'invalid email' => [['--email' => 'bukan-email'], 'alamat email yang valid'],
    'email already used' => [['--email' => 'dipakai@example.com'], 'sudah dipakai'],
    'weak password' => [['--password' => 'abc'], 'minimal terdiri dari 8 karakter'],
    'invalid phone' => [['--phone' => '123'], 'Nomor WhatsApp'],
    'empty name' => [['--name' => ''], 'wajib diisi'],
]);
