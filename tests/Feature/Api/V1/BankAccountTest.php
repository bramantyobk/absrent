<?php

use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\User;

function bankPayload(array $overrides = []): array
{
    return $overrides + [
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'ABSRENT Rental',
    ];
}

it('rejects guests, customers, and operators on the bank account endpoints', function (string $method, string $route, bool $needsAccount) {
    $parameters = $needsAccount ? [BankAccount::factory()->create()] : [];

    $this->{$method}(route($route, $parameters))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->{$method}(route($route, $parameters))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route, $parameters))->assertForbidden();
})->with([
    ['getJson', 'api.v1.dashboard.company.bank-accounts.index', false],
    ['getJson', 'api.v1.dashboard.company.bank-accounts.show', true],
    ['postJson', 'api.v1.dashboard.company.bank-accounts.store', false],
    ['putJson', 'api.v1.dashboard.company.bank-accounts.update', true],
]);

it('lists all accounts in display order', function () {
    BankAccount::factory()->create(['bank_name' => 'BNI', 'account_number' => '3333333333', 'sort_order' => 2]);
    BankAccount::factory()->inactive()->create(['bank_name' => 'BCA', 'account_number' => '1111111111', 'sort_order' => 1]);
    $this->actingAs(User::factory()->admin()->create());

    $this->getJson(route('api.v1.dashboard.company.bank-accounts.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.bank_name', 'BCA')
        ->assertJsonPath('data.0.is_active', false);
});

it('creates an active account at the end of the list, and rejects duplicates on the same bank', function () {
    CompanyProfile::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->postJson(route('api.v1.dashboard.company.bank-accounts.store'), bankPayload())
        ->assertCreated()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.sort_order', 1);

    $this->postJson(route('api.v1.dashboard.company.bank-accounts.store'), bankPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account_number');
});

it('updates and deactivates an account but offers no delete endpoint', function () {
    $account = BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '1234567890']);
    $this->actingAs(User::factory()->admin()->create());

    $this->putJson(route('api.v1.dashboard.company.bank-accounts.update', $account), bankPayload(['account_holder' => 'PT Baru']))
        ->assertOk()
        ->assertJsonPath('data.account_holder', 'PT Baru')
        ->assertJsonPath('data.is_active', true);

    $this->putJson(route('api.v1.dashboard.company.bank-accounts.update', $account), bankPayload(['is_active' => false]))
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("/api/v1/dashboard/company/bank-accounts/{$account->id}")->assertStatus(405);
});

it('includes the ordered bank accounts in the company response', function () {
    $company = CompanyProfile::factory()->create();
    BankAccount::factory()->for($company, 'companyProfile')->create(['bank_name' => 'BNI', 'account_number' => '3333333333', 'sort_order' => 2]);
    BankAccount::factory()->for($company, 'companyProfile')->create(['bank_name' => 'BCA', 'account_number' => '1111111111', 'sort_order' => 1]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('api.v1.dashboard.company.show'))
        ->assertOk()
        ->assertJsonCount(2, 'data.bank_accounts')
        ->assertJsonPath('data.bank_accounts.0.bank_name', 'BCA');
});
