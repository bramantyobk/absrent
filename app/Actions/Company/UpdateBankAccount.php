<?php

namespace App\Actions\Company;

use App\Models\BankAccount;

class UpdateBankAccount
{
    /**
     * Urutan yang dikosongkan tidak mengubah urutan yang ada.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(BankAccount $bankAccount, array $data): BankAccount
    {
        if (($data['sort_order'] ?? null) === null) {
            unset($data['sort_order']);
        }

        $bankAccount->update($data);

        return $bankAccount;
    }
}
