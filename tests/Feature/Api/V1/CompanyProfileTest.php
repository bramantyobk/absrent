<?php

use App\Models\CompanyProfile;
use App\Models\User;

function companyPayload(array $overrides = []): array
{
    $hours = array_map(fn (string $day): array => [
        'day' => $day, 'is_open' => true, 'open_at' => '09:00', 'close_at' => '16:00',
    ], ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
    $hours[] = ['day' => 'saturday', 'is_open' => false];
    $hours[] = ['day' => 'sunday', 'is_open' => false];

    return $overrides + [
        'name' => 'ABSRENT Rental',
        'address' => 'Jl. Merdeka No. 10, Bandung',
        'support_whatsapp' => '6281234567890',
        'operating_hours' => $hours,
    ];
}

it('rejects guests, customers, and operators on the company endpoints', function (string $method, string $route) {
    $this->{$method}(route($route))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->{$method}(route($route))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route))->assertForbidden();
})->with([
    ['getJson', 'api.v1.dashboard.company.show'],
    ['putJson', 'api.v1.dashboard.company.update'],
]);

it('shows the company with seven days of office hours', function () {
    CompanyProfile::factory()->create(['name' => 'Rental Maju']);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('api.v1.dashboard.company.show'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Rental Maju')
        ->assertJsonCount(7, 'data.operating_hours')
        ->assertJsonPath('data.operating_hours.0.label', 'Senin')
        ->assertJsonPath('data.operating_hours.6.is_open', false);
});

it('updates the company through the api', function () {
    $company = CompanyProfile::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->putJson(route('api.v1.dashboard.company.update'), companyPayload(['support_whatsapp' => '081234567890']))
        ->assertOk()
        ->assertJsonPath('data.support_whatsapp', '6281234567890')
        ->assertJsonPath('data.operating_hours.0.open_at', '09:00')
        ->assertJsonPath('data.operating_hours.5.open_at', null);

    expect($company->refresh()->address)->toBe('Jl. Merdeka No. 10, Bandung');
});

it('returns field errors for invalid company input', function () {
    CompanyProfile::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->putJson(route('api.v1.dashboard.company.update'), companyPayload(['name' => '', 'support_whatsapp' => '123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'support_whatsapp']);
});
