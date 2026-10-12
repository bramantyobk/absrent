<?php

namespace App\Actions\Company;

use App\Models\BankAccount;
use App\Models\CompanyProfile;

class CreateBankAccount
{
    /**
     * Rekening dibuat aktif dan, bila urutan dikosongkan, ditempatkan di akhir daftar.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): BankAccount
    {
        $company = CompanyProfile::current();

        $data['is_active'] ??= true;
        $data['sort_order'] ??= $company->bankAccounts()->max('sort_order') + 1;

        return $company->bankAccounts()->create($data);
    }
}
