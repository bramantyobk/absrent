<?php

use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Transaction;
use App\Models\User;

function bankAccountData(array $overrides = []): array
{
    return $overrides + [
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'ABSRENT Rental',
        'is_active' => '1',
    ];
}

it('limits bank account actions to admins', function (string $method, string $route) {
    $account = BankAccount::factory()->create();

    $this->{$method}(route($route, $account))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->{$method}(route($route, $account))->assertForbidden();
    $this->actingAs(User::factory()->operator()->create())->{$method}(route($route, $account))->assertForbidden();
})->with([
    ['post', 'dashboard.company.bank-accounts.store'],
    ['put', 'dashboard.company.bank-accounts.update'],
]);

it('lists bank accounts in order on the company page and warns when none is active', function () {
    $company = CompanyProfile::factory()->create();
    BankAccount::factory()->for($company, 'companyProfile')->create(['bank_name' => 'Mandiri', 'account_number' => '2222222222', 'sort_order' => 2]);
    BankAccount::factory()->for($company, 'companyProfile')->inactive()->create(['bank_name' => 'BCA', 'account_number' => '1111111111', 'sort_order' => 1]);
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard.company.edit'))
        ->assertOk()
        ->assertSeeInOrder(['BCA', '1111111111', 'Nonaktif', 'Mandiri', '2222222222', 'Aktif'])
        ->assertDontSee('Belum ada rekening aktif');

    BankAccount::query()->update(['is_active' => false]);

    $this->get(route('dashboard.company.edit'))->assertSee('Belum ada rekening aktif');
});

it('adds a bank account to the company with normalized values, active by default, at the end of the list', function () {
    $company = CompanyProfile::factory()->create();
    BankAccount::factory()->for($company, 'companyProfile')->create(['sort_order' => 4]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.company.bank-accounts.store'), array_diff_key(bankAccountData([
            'bank_name' => '  Bank   Mandiri ',
            'account_number' => '1234-5678 90',
            'sort_order' => '',
        ]), ['is_active' => true]))
        ->assertRedirect(route('dashboard.company.edit'))
        ->assertSessionHasNoErrors();

    $account = BankAccount::query()->latest('id')->first();

    expect($account->company_profile_id)->toBe($company->id)
        ->and($account->bank_name)->toBe('Bank Mandiri')
        ->and($account->account_number)->toBe('1234567890')
        ->and($account->is_active)->toBeTrue()
        ->and($account->sort_order)->toBe(5);
});

it('validates bank account input', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('dashboard.company.bank-accounts.store'), bankAccountData($overrides))
        ->assertSessionHasErrors($field, errorBag: 'bank_account');

    expect(BankAccount::count())->toBe(0);
})->with([
    'missing bank' => [['bank_name' => ''], 'bank_name'],
    'missing number' => [['account_number' => ''], 'account_number'],
    'letters in number' => [['account_number' => '12AB345'], 'account_number'],
    'number too short' => [['account_number' => '1234'], 'account_number'],
    'missing holder' => [['account_holder' => ''], 'account_holder'],
    'negative order' => [['sort_order' => '-1'], 'sort_order'],
]);

it('rejects the same account number on the same bank but allows it on another bank', function () {
    BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '1234567890']);
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('dashboard.company.bank-accounts.store'), bankAccountData())
        ->assertSessionHasErrors('account_number', errorBag: 'bank_account');

    $this->post(route('dashboard.company.bank-accounts.store'), bankAccountData(['bank_name' => 'BNI']))
        ->assertSessionHasNoErrors();
});

it('updates a bank account, keeping its own number and its order when left blank', function () {
    $account = BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '1234567890', 'sort_order' => 3]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('dashboard.company.bank-accounts.update', $account), bankAccountData(['account_holder' => 'PT Baru', 'sort_order' => '']))
        ->assertRedirect(route('dashboard.company.edit'))
        ->assertSessionHasNoErrors();

    expect($account->refresh())->account_holder->toBe('PT Baru')->sort_order->toBe(3);
});

it('deactivates a bank account that transactions already use instead of deleting it', function () {
    $account = BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '1234567890']);
    $transaction = Transaction::factory()->create(['bank_account_id' => $account->id]);
    $this->actingAs(User::factory()->admin()->create());

    $this->delete("/dashboard/company/bank-accounts/{$account->id}")->assertStatus(405);

    $this->put(route('dashboard.company.bank-accounts.update', $account), bankAccountData(['is_active' => '0']))
        ->assertSessionHasNoErrors();

    expect($account->fresh()->is_active)->toBeFalse()
        ->and($transaction->fresh()->bank_account_id)->toBe($account->id);
});

it('scopes active accounts in display order for the payment page', function () {
    BankAccount::factory()->create(['bank_name' => 'BNI', 'account_number' => '3333333333', 'sort_order' => 2]);
    BankAccount::factory()->create(['bank_name' => 'BCA', 'account_number' => '1111111111', 'sort_order' => 1]);
    BankAccount::factory()->inactive()->create(['bank_name' => 'BRI', 'account_number' => '2222222222', 'sort_order' => 0]);

    expect(BankAccount::query()->active()->ordered()->pluck('bank_name')->all())->toBe(['BCA', 'BNI']);
});
