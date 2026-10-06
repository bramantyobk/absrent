<?php

use App\Models\Order;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Hash;

function validUserData(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Budi Operator',
        'email' => 'budi@example.com',
        'phone' => '0812-3456-7890',
        'password' => 'password-rahasia',
        'password_confirmation' => 'password-rahasia',
        'role' => 'operator',
        'is_active' => '1',
        'is_on_duty' => '1',
    ];
}

it('requires the admin role for every user management action', function (string $method, string $route) {
    $target = User::factory()->create();

    $this->{$method}(route($route, $target))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->operator()->create())
        ->{$method}(route($route, $target))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $target))
        ->assertForbidden();
})->with([
    ['get', 'dashboard.users.create'],
    ['get', 'dashboard.users.edit'],
    ['post', 'dashboard.users.store'],
    ['put', 'dashboard.users.update'],
    ['delete', 'dashboard.users.destroy'],
    ['patch', 'dashboard.users.duty-status'],
]);

it('lists users with search, role, and status filters', function () {
    User::factory()->create(['name' => 'Andi Pelanggan']);
    User::factory()->operator()->create(['name' => 'Rina Operator', 'email' => 'rina@absrent.test']);
    User::factory()->operator()->inactive()->create(['name' => 'Tono Operator']);

    $this->actingAs(User::factory()->admin()->create(['name' => 'Admin Utama']));

    $names = fn (array $query) => $this->get(route('dashboard.users.index', $query))
        ->assertOk()
        ->viewData('users')
        ->pluck('name')
        ->all();

    expect($names([]))->toHaveCount(4)
        ->and($names(['search' => 'rina']))->toBe(['Rina Operator'])
        ->and($names(['role' => 'operator']))->toBe(['Rina Operator', 'Tono Operator'])
        ->and($names(['role' => 'operator', 'status' => 'inactive']))->toBe(['Tono Operator'])
        ->and($names(['status' => 'active']))->toHaveCount(3);
});

it('paginates the user list by ten', function () {
    User::factory()->count(14)->create();

    $this->actingAs(User::factory()->admin()->create());

    $users = $this->get(route('dashboard.users.index'))->viewData('users');

    expect($users->total())->toBe(15)
        ->and($users->perPage())->toBe(10)
        ->and($users->items())->toHaveCount(10);
});

it('rejects invalid list filters', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.users.index', ['role' => 'boss', 'status' => 'maybe']))
        ->assertSessionHasErrors(['role', 'status']);
});

it('renders the create and edit pages with the role options', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->create();

    $this->get(route('dashboard.users.create'))->assertOk()->assertViewHas('roles', UserRole::cases());
    $this->get(route('dashboard.users.edit', $target))->assertOk()->assertViewHas('user', fn (User $user) => $user->is($target));
});

it('creates a user with a hashed password and a normalized phone number', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.users.store'), validUserData())
        ->assertRedirect(route('dashboard.users.index'))
        ->assertSessionHas('status');

    $user = User::where('email', 'budi@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Operator)
        ->and($user->phone)->toBe('6281234567890')
        ->and($user->is_active)->toBeTrue()
        ->and($user->is_on_duty)->toBeTrue()
        ->and(Hash::check('password-rahasia', $user->password))->toBeTrue();
});

it('only keeps the duty flag for active operators', function (array $overrides) {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.users.store'), validUserData($overrides));

    expect(User::where('email', 'budi@example.com')->firstOrFail()->is_on_duty)->toBeFalse();
})->with([
    'customer' => [['role' => 'user']],
    'admin' => [['role' => 'admin']],
    'inactive operator' => [['is_active' => '0']],
]);

it('validates user input on create', function (array $overrides, string $field) {
    User::factory()->create(['email' => 'dipakai@example.com']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.users.store'), validUserData($overrides))
        ->assertSessionHasErrors($field);

    expect(User::where('name', 'Budi Operator')->exists())->toBeFalse();
})->with([
    'duplicate email' => [['email' => 'dipakai@example.com'], 'email'],
    'invalid phone' => [['phone' => '123'], 'phone'],
    'password mismatch' => [['password_confirmation' => 'beda'], 'password'],
    'short password' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
    'unknown role' => [['role' => 'superadmin'], 'role'],
    'missing name' => [['name' => ''], 'name'],
]);

it('updates a user and keeps the password when it is left blank', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->create(['name' => 'Lama']);
    $originalPassword = $target->password;

    $this->put(route('dashboard.users.update', $target), validUserData([
        'name' => 'Baru',
        'email' => $target->email,
        'password' => '',
        'password_confirmation' => '',
        'role' => 'operator',
    ]))->assertRedirect(route('dashboard.users.index'));

    $target->refresh();

    expect($target->name)->toBe('Baru')
        ->and($target->role)->toBe(UserRole::Operator)
        ->and($target->password)->toBe($originalPassword);
});

it('resets the password when a new one is provided', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->create();

    $this->put(route('dashboard.users.update', $target), validUserData([
        'email' => $target->email,
        'role' => 'user',
        'password' => 'password-baru-123',
        'password_confirmation' => 'password-baru-123',
    ]));

    expect(Hash::check('password-baru-123', $target->refresh()->password))->toBeTrue();
});

it('allows keeping the same email on update but rejects another user\'s email', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->create();
    $other = User::factory()->create();

    $this->put(route('dashboard.users.update', $target), validUserData(['email' => $target->email, 'role' => 'user']))
        ->assertSessionHasNoErrors();

    $this->put(route('dashboard.users.update', $target), validUserData(['email' => $other->email, 'role' => 'user']))
        ->assertSessionHasErrors('email');
});

it('resets the duty flag when an operator is deactivated or changes role', function (array $overrides) {
    $this->actingAs(User::factory()->admin()->create());
    $operator = User::factory()->operator()->onDuty()->create();

    $this->put(route('dashboard.users.update', $operator), validUserData($overrides + ['email' => $operator->email]));

    expect($operator->refresh()->is_on_duty)->toBeFalse();
})->with([
    'deactivated' => [['is_active' => '0']],
    'demoted to customer' => [['role' => 'user']],
]);

it('stops an admin from demoting or deactivating their own account', function (array $overrides, string $field) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('dashboard.users.update', $admin), validUserData($overrides + ['email' => $admin->email, 'role' => 'admin']))
        ->assertSessionHasErrors($field);

    expect($admin->refresh())->role->toBe(UserRole::Admin)->is_active->toBeTrue();
})->with([
    'demote' => [['role' => 'user'], 'role'],
    'deactivate' => [['is_active' => '0'], 'is_active'],
]);

it('lets an admin change another admin', function () {
    $this->actingAs(User::factory()->admin()->create());
    $otherAdmin = User::factory()->admin()->create();

    $this->put(route('dashboard.users.update', $otherAdmin), validUserData(['email' => $otherAdmin->email, 'role' => 'user']))
        ->assertSessionHasNoErrors();

    expect($otherAdmin->refresh()->role)->toBe(UserRole::User);
});

it('deletes a user that has no history', function () {
    $this->actingAs(User::factory()->admin()->create());
    $target = User::factory()->create();

    $this->delete(route('dashboard.users.destroy', $target))
        ->assertRedirect(route('dashboard.users.index'))
        ->assertSessionHas('status');

    expect(User::find($target->id))->toBeNull();
});

it('refuses to delete a user with orders', function () {
    $this->actingAs(User::factory()->admin()->create());
    $customer = User::factory()->create();
    Order::factory()->for($customer, 'user')->create();

    $this->delete(route('dashboard.users.destroy', $customer))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(User::find($customer->id))->not->toBeNull();
});

it('refuses to delete an admin\'s own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('dashboard.users.destroy', $admin))
        ->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

it('shows the user table with actions, role badges, and the duty switch for operators only', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Utama']);
    $operator = User::factory()->operator()->onDuty()->create(['name' => 'Rina Operator']);
    $customer = User::factory()->create(['name' => 'Andi Pelanggan', 'phone' => '6281234567890']);

    $this->actingAs($admin)
        ->get(route('dashboard.users.index'))
        ->assertOk()
        ->assertSee('Daftar Pengguna')
        ->assertSee('Rina Operator')
        ->assertSee('Andi Pelanggan')
        ->assertSee('https://wa.me/6281234567890', false)
        ->assertSee(route('dashboard.users.edit', $customer), false)
        ->assertSee(route('dashboard.users.destroy', $customer), false)
        ->assertSee(route('dashboard.users.duty-status', $operator), false)
        ->assertDontSee(route('dashboard.users.duty-status', $customer), false)
        ->assertSee('Bertugas');
});

it('disables deleting your own account in the table', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard.users.index'))
        ->assertSee('Anda')
        ->assertDontSee('data-delete-url="'.route('dashboard.users.destroy', $admin).'"', false);
});

it('shows an empty state and keeps filter values in the form', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.users.index', ['search' => 'tidak-ada-orang', 'role' => 'operator', 'status' => 'inactive']))
        ->assertOk()
        ->assertSee('Tidak ada pengguna yang cocok.')
        ->assertSee('value="tidak-ada-orang"', false);
});

it('shows flash messages after saving and deleting', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->followingRedirects()
        ->post(route('dashboard.users.store'), validUserData())
        ->assertSee('Pengguna berhasil ditambahkan.');

    $customer = User::factory()->create();
    Order::factory()->for($customer, 'user')->create();

    $this->followingRedirects()
        ->from(route('dashboard.users.index'))
        ->delete(route('dashboard.users.destroy', $customer))
        ->assertSee('Nonaktifkan akunnya sebagai gantinya.');
});

it('renders the create form with every field', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.users.create'))
        ->assertOk()
        ->assertSee('Tambah Pengguna')
        ->assertSee('name="name"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="phone"', false)
        ->assertSee('name="role"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertSee('name="is_active"', false)
        ->assertSee('name="is_on_duty"', false);
});

it('prefills the edit form and locks role and status for your own account', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Utama']);
    $other = User::factory()->operator()->create(['name' => 'Rina Operator']);

    $this->actingAs($admin)
        ->get(route('dashboard.users.edit', $admin))
        ->assertOk()
        ->assertSee('value="Admin Utama"', false)
        ->assertSee('Anda tidak dapat mengubah role akun sendiri.')
        ->assertSee('Anda tidak dapat menonaktifkan akun sendiri.');

    $this->get(route('dashboard.users.edit', $other))
        ->assertOk()
        ->assertSee('value="Rina Operator"', false)
        ->assertDontSee('Anda tidak dapat mengubah role akun sendiri.');
});

it('redisplays the form with errors and the old input when validation fails', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->from(route('dashboard.users.create'))
        ->followingRedirects()
        ->post(route('dashboard.users.store'), validUserData(['phone' => '123']))
        ->assertSee('value="Budi Operator"', false)
        ->assertSee('is-invalid', false)
        ->assertSee('Nomor WhatsApp tidak valid.');
});

it('lets staff view the user list but not guests or customers', function () {
    $this->get(route('dashboard.users.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.users.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard.users.index'))
        ->assertOk();
});

it('shows the user list read-only to operators', function () {
    $operator = User::factory()->operator()->onDuty()->create(['name' => 'Rina Operator']);
    $customer = User::factory()->create(['name' => 'Andi Pelanggan']);
    $offDuty = User::factory()->operator()->create(['name' => 'Tono Operator']);

    $this->actingAs($operator)
        ->get(route('dashboard.users.index'))
        ->assertOk()
        ->assertSee('Daftar Pengguna')
        ->assertSee('Andi Pelanggan')
        ->assertSee('Tono Operator')
        ->assertSee('Bertugas')
        ->assertSee('Libur')
        ->assertDontSee('Tambah Pengguna')
        ->assertDontSee('Aksi')
        ->assertDontSee(route('dashboard.users.create'), false)
        ->assertDontSee(route('dashboard.users.edit', $customer), false)
        ->assertDontSee('data-delete-url', false)
        ->assertDontSee('data-duty-toggle', false)
        ->assertDontSee(route('dashboard.users.duty-status', $offDuty), false)
        ->assertDontSee('deleteUserModal', false);
});

it('keeps the filters working for operators', function () {
    User::factory()->create(['name' => 'Andi Pelanggan']);
    User::factory()->operator()->create(['name' => 'Rina Operator']);

    $this->actingAs(User::factory()->operator()->create(['name' => 'Penonton']))
        ->get(route('dashboard.users.index', ['role' => 'user']))
        ->assertOk()
        ->assertSee('Andi Pelanggan')
        ->assertDontSee('Rina Operator');
});
