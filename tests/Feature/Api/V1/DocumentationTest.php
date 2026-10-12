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
