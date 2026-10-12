<?php

use App\Models\CompanyProfile;
use App\Models\User;

function companyData(array $overrides = []): array
{
    $hours = array_map(fn (string $day): array => [
        'day' => $day, 'is_open' => '1', 'open_at' => '08:00', 'close_at' => '17:00',
    ], ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
    $hours[] = ['day' => 'sunday', 'is_open' => '0'];

    return $overrides + [
        'name' => 'ABSRENT Rental',
        'address' => 'Jl. Merdeka No. 10, Bandung',
        'support_whatsapp' => '081234567890',
        'operating_hours' => $hours,
    ];
}

it('limits the company page to admins', function (string $method, string $route) {
    $this->{$method}(route($route))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->{$method}(route($route))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route))->assertForbidden();
})->with([
    ['get', 'dashboard.company.edit'],
    ['put', 'dashboard.company.update'],
]);

it('shows the company data and the seven days of office hours to an admin', function () {
    CompanyProfile::factory()->create(['name' => 'Rental Maju', 'address' => 'Jl. Kenanga 5', 'support_whatsapp' => '6281200000000']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.company.edit'))
        ->assertOk()
        ->assertSee('Rental Maju')
        ->assertSee('Jl. Kenanga 5')
        ->assertSee('6281200000000')
        ->assertSee('Jam Layanan Kantor');

    expect($response->viewData('hours'))->toHaveCount(7)
        ->and(collect($response->viewData('hours'))->pluck('label')->all())
        ->toBe(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']);
});

it('creates the single company row on first visit and falls back to default hours', function () {
    expect(CompanyProfile::count())->toBe(0);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard.company.edit'))
        ->assertOk();

    $hours = collect($response->viewData('hours'))->keyBy('day');

    expect(CompanyProfile::count())->toBe(1)
        ->and($hours['monday'])->toMatchArray(['is_open' => true, 'open_at' => '08:00', 'close_at' => '17:00'])
        ->and($hours['sunday'])->toMatchArray(['is_open' => false, 'open_at' => null, 'close_at' => null]);
});

it('updates the company, normalizes the phone number, and clears hours on closed days', function () {
    $company = CompanyProfile::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.company.update'), companyData([
            'name' => '  Rental Baru  ',
            'operating_hours' => array_replace(companyData()['operating_hours'], [
                5 => ['day' => 'saturday', 'is_open' => '0', 'open_at' => '09:00', 'close_at' => '12:00'],
            ]),
        ]))
        ->assertRedirect(route('dashboard.company.edit'))
        ->assertSessionHasNoErrors();

    $company->refresh();
    $hours = collect($company->operating_hours)->keyBy('day');

    expect(CompanyProfile::count())->toBe(1)
        ->and($company->name)->toBe('Rental Baru')
        ->and($company->address)->toBe('Jl. Merdeka No. 10, Bandung')
        ->and($company->support_whatsapp)->toBe('6281234567890')
        ->and($company->operating_hours)->toHaveCount(7)
        ->and($hours['monday']['open_at'])->toBe('08:00')
        ->and($hours['saturday'])->toMatchArray(['is_open' => false, 'open_at' => null, 'close_at' => null]);
});

it('stores office hours in Monday to Sunday order even when sent shuffled', function () {
    $company = CompanyProfile::factory()->create();
    $data = companyData();
    $data['operating_hours'] = array_reverse($data['operating_hours']);

    $this->actingAs(User::factory()->admin()->create())->put(route('dashboard.company.update'), $data)->assertSessionHasNoErrors();

    expect(collect($company->refresh()->operating_hours)->pluck('day')->first())->toBe('monday')
        ->and(collect($company->operating_hours)->pluck('day')->last())->toBe('sunday');
});

it('validates the company input', function (array $overrides, string $field) {
    CompanyProfile::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.company.update'), companyData($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing address' => [['address' => ''], 'address'],
    'invalid whatsapp' => [['support_whatsapp' => '12345'], 'support_whatsapp'],
    'missing hours' => [['operating_hours' => []], 'operating_hours'],
    'open day without times' => [['operating_hours' => array_replace(companyData()['operating_hours'], [0 => ['day' => 'monday', 'is_open' => '1']])], 'operating_hours.0.open_at'],
    'closing before opening' => [['operating_hours' => array_replace(companyData()['operating_hours'], [0 => ['day' => 'monday', 'is_open' => '1', 'open_at' => '17:00', 'close_at' => '08:00']])], 'operating_hours.0.close_at'],
    'invalid time' => [['operating_hours' => array_replace(companyData()['operating_hours'], [0 => ['day' => 'monday', 'is_open' => '1', 'open_at' => '25:00', 'close_at' => '26:00']])], 'operating_hours.0.open_at'],
    'duplicate day' => [['operating_hours' => array_replace(companyData()['operating_hours'], [1 => ['day' => 'monday', 'is_open' => '0']])], 'operating_hours.1.day'],
]);

it('shows the company menu to admins only', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertSee('Data Perusahaan');

    $this->actingAs(User::factory()->operator()->create())
        ->get(route('dashboard'))
        ->assertDontSee('Data Perusahaan');
});
