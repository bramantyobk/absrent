<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

function resetLinkFor(User $customer): string
{
    return Password::broker()->createToken($customer);
}

it('offers a forgot password link on the login page', function () {
    $this->get(route('login'))->assertSee(route('password.request'), false);
    $this->get(route('password.request'))->assertOk()->assertSee('Lupa password');
});

it('records a request for a customer without revealing anything', function () {
    $customer = User::factory()->create(['email' => 'budi@example.com']);

    $this->post(route('password.email'), ['email' => 'budi@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($customer->refresh()->password_reset_requested_at)->not->toBeNull();
});

it('gives the same answer for unknown, staff, and inactive accounts and records nothing', function (callable $email) {
    $address = $email();

    $this->post(route('password.email'), ['email' => $address])
        ->assertRedirect()
        ->assertSessionHas('status', 'Permintaan diterima. Admin akan mengirim tautan reset ke WhatsApp Anda.')
        ->assertSessionHasNoErrors();

    expect(User::whereNotNull('password_reset_requested_at')->count())->toBe(0);
})->with([
    'unknown email' => [fn () => 'tidak-ada@example.com'],
    'operator' => [fn () => User::factory()->operator()->create()->email],
    'admin' => [fn () => User::factory()->admin()->create()->email],
    'inactive customer' => [fn () => User::factory()->inactive()->create()->email],
]);

it('rejects a malformed email and throttles repeated requests', function () {
    $this->post(route('password.email'), ['email' => 'bukan-email'])->assertSessionHasErrors('email');

    foreach (range(1, 6) as $attempt) {
        $this->post(route('password.email'), ['email' => 'a@example.com']);
    }

    $this->post(route('password.email'), ['email' => 'a@example.com'])->assertStatus(429);
});

it('shows the pending requests banner to admins only and hides it when there are none', function () {
    User::factory()->count(2)->create(['password_reset_requested_at' => now()]);
    User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertSee('2 pengajuan lupa password')
        ->assertSee(route('dashboard.users.index', ['reset' => 'requested']), false);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard'))
        ->assertDontSee('pengajuan lupa password');

    User::query()->update(['password_reset_requested_at' => null]);

    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertDontSee('pengajuan lupa password');
});

it('marks requesting customers in the user list and filters them for admins', function () {
    $requesting = User::factory()->create(['name' => 'Pemohon Reset', 'password_reset_requested_at' => now()]);
    User::factory()->create(['name' => 'Pelanggan Biasa']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.users.index', ['reset' => 'requested']))
        ->assertOk()
        ->assertSee('Pemohon Reset')
        ->assertDontSee('Pelanggan Biasa')
        ->assertSee('Minta reset password')
        ->assertSee(route('dashboard.users.password-reset-link', $requesting), false);

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.users.index'))
        ->assertSee('Minta reset password')
        ->assertDontSee(route('dashboard.users.password-reset-link', $requesting), false);
});

it('lets only an admin issue a reset link for a customer and sends it through whatsapp', function () {
    $customer = User::factory()->create(['name' => 'Budi', 'phone' => '6281234567890', 'password_reset_requested_at' => now()]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.users.password-reset-link', $customer));

    $location = $response->headers->get('Location');
    parse_str(parse_url($location, PHP_URL_QUERY), $query);

    $response->assertRedirect();
    expect($location)->toStartWith('https://wa.me/6281234567890?text=')
        ->and($query['text'])->toContain('Budi')->toContain('/reset-password/')->toContain('10 menit')
        ->and($customer->refresh()->password_reset_requested_at)->toBeNull();
});

it('rejects issuing a reset link for staff or by non admins', function () {
    $customer = User::factory()->create();

    $this->post(route('dashboard.users.password-reset-link', $customer))->assertRedirect(route('login'));
    $this->actingAs($customer)->post(route('dashboard.users.password-reset-link', $customer))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->post(route('dashboard.users.password-reset-link', $customer))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.users.password-reset-link', User::factory()->operator()->create()))
        ->assertForbidden();
});

it('resets the password with a valid token once and clears the request', function () {
    $customer = User::factory()->create(['password' => 'password-lama-1', 'password_reset_requested_at' => now()]);
    $token = resetLinkFor($customer);
    $payload = ['token' => $token, 'email' => $customer->email, 'password' => 'Password-Baru-9', 'password_confirmation' => 'Password-Baru-9'];

    $this->get(route('password.reset', ['token' => $token, 'email' => $customer->email]))->assertOk()->assertSee('Password baru');

    $this->post(route('password.update'), $payload)->assertRedirect(route('login'))->assertSessionHas('status');

    expect(Hash::check('Password-Baru-9', $customer->refresh()->password))->toBeTrue()
        ->and($customer->password_reset_requested_at)->toBeNull();

    $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
});

it('accepts the token within 10 minutes and rejects it after', function (int $minutes, bool $accepted) {
    $customer = User::factory()->create(['password' => 'password-lama-1']);
    $token = resetLinkFor($customer);

    $this->travel($minutes)->minutes();

    $response = $this->post(route('password.update'), ['token' => $token, 'email' => $customer->email, 'password' => 'Password-Baru-9', 'password_confirmation' => 'Password-Baru-9']);

    $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('email');
    expect(Hash::check('Password-Baru-9', $customer->refresh()->password))->toBe($accepted);
})->with([
    'after 9 minutes' => [9, true],
    'after 11 minutes' => [11, false],
]);

it('rejects a wrong token, a weak password, and a mismatched confirmation', function (array $overrides) {
    $customer = User::factory()->create(['password' => 'password-lama-1']);
    $token = resetLinkFor($customer);

    $this->post(route('password.update'), $overrides + ['token' => $token, 'email' => $customer->email, 'password' => 'Password-Baru-9', 'password_confirmation' => 'Password-Baru-9'])
        ->assertSessionHasErrors();

    expect(Hash::check('password-lama-1', $customer->refresh()->password))->toBeTrue();
})->with([
    'wrong token' => [['token' => 'salah']],
    'weak password' => [['password' => 'a1', 'password_confirmation' => 'a1']],
    'mismatch' => [['password_confirmation' => 'lain']],
]);

it('keeps the reset token lifetime at 10 minutes', function () {
    expect(config('auth.passwords.users.expire'))->toBe(10);
});
