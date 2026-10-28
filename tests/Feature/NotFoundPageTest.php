<?php

use App\Models\User;
use App\Models\Vehicle;

it('shows a friendly page with links back for an unknown address', function () {
    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee(route('home'), false)
        ->assertSee(route('vehicles.index'), false)
        ->assertDontSee(route('dashboard'), false);
});

it('uses the same page when a controller aborts with 404', function () {
    $hidden = Vehicle::factory()->unavailable()->create();

    $this->get(route('vehicles.show', $hidden))->assertNotFound()->assertSee('Halaman tidak ditemukan');
});

it('offers staff a way back to the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee(route('dashboard'), false);
});

it('keeps answering api requests with json', function () {
    $this->getJson('/api/v1/tidak-ada')->assertNotFound()->assertJsonStructure(['message']);
});
