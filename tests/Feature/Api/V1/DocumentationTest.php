<?php

use App\Models\User;

it('serves the openapi document to admins only', function () {
    $this->getJson('/docs/api.json')->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->getJson('/docs/api.json')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('info.title', config('app.name').' API')
        ->assertJsonStructure(['paths' => ['/auth/login', '/auth/register', '/auth/me', '/dashboard/vehicles']]);
});

it('documents the customer account, terms, settings, and password reset endpoints', function () {
    $paths = $this->actingAs(User::factory()->admin()->create())->getJson('/docs/api.json')->assertOk()->json('paths');

    expect($paths)->toHaveKeys([
        '/auth/forgot-password',
        '/auth/reset-password',
        '/profile',
        '/profile/photo',
        '/profile/password',
        '/terms',
        '/checkout',
        '/dashboard/terms',
        '/dashboard/settings/rental',
        '/dashboard/users/{user}/password-reset-link',
        '/dashboard/equipment/{equipment}',
    ]);
});
