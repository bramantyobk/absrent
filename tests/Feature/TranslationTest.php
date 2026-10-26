<?php

it('has every english language line translated to indonesian', function (string $file) {
    $missing = array_diff_key(
        Arr::dot(require lang_path("en/{$file}.php")),
        Arr::dot(require lang_path("id/{$file}.php")),
    );

    $missing = array_filter(array_keys($missing), fn (string $key): bool => ! str_starts_with($key, 'custom.') && $key !== 'attributes');

    expect($missing)->toBe([]);
})->with(['validation', 'auth', 'passwords', 'pagination']);

it('shows validation messages in indonesian instead of raw translation keys', function () {
    app()->setLocale('id');

    $this->post(route('password.email'), ['email' => ''])
        ->assertSessionHasErrors(['email' => 'Kolom email wajib diisi.']);

    $this->post(route('password.email'), ['email' => 'bukan-email'])
        ->assertSessionHasErrors(['email' => 'Kolom email harus berupa alamat email yang valid.']);
});

it('uses readable indonesian names for the fields of the application', function () {
    app()->setLocale('id');

    $this->post(route('register'), ['name' => '', 'email' => 'a@example.com', 'phone' => '081234567890', 'password' => 'Password-Kuat-9', 'password_confirmation' => 'beda'])
        ->assertSessionHasErrors(['name' => 'Kolom nama wajib diisi.', 'password' => 'Konfirmasi password tidak cocok.']);
});
