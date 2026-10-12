<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Company\CreateBankAccount;
use App\Actions\Company\UpdateBankAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;

class BankAccountController extends Controller
{
    public function store(StoreBankAccountRequest $request, CreateBankAccount $createBankAccount): RedirectResponse
    {
        $createBankAccount->handle($request->validated());

        return redirect()
            ->route('dashboard.company.edit')
            ->with('status', 'Rekening bank berhasil ditambahkan.');
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount, UpdateBankAccount $updateBankAccount): RedirectResponse
    {
        $updateBankAccount->handle($bankAccount, $request->validated());

        return redirect()
            ->route('dashboard.company.edit')
            ->with('status', 'Rekening bank berhasil diperbarui.');
    }
}
