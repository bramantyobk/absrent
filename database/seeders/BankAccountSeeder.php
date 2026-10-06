<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = CompanyProfile::query()->firstOrFail();

        $accounts = [
            ['BCA', '1234567890', true],
            ['Mandiri', '9876543210', true],
            ['BRI', '1122334455', true],
            ['BNI', '5544332211', false],
        ];

        foreach ($accounts as $index => [$bankName, $accountNumber, $isActive]) {
            BankAccount::factory()->for($company, 'companyProfile')->create([
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'account_holder' => $company->name,
                'is_active' => $isActive,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
