<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Company\CreateBankAccount;
use App\Actions\Company\UpdateBankAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BankAccountController extends Controller
{
    /**
     * Daftar rekening bank
     *
     * Semua rekening (aktif dan nonaktif) berurutan menurut urutan tampil. Khusus admin.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', BankAccount::class);

        return BankAccountResource::collection(BankAccount::query()->ordered()->get());
    }

    /**
     * Detail rekening bank
     */
    public function show(BankAccount $bankAccount): BankAccountResource
    {
        Gate::authorize('view', $bankAccount);

        return new BankAccountResource($bankAccount);
    }

    /**
     * Tambah rekening bank
     *
     * Dibuat aktif. Urutan yang dikosongkan ditempatkan di akhir daftar. Nomor rekening harus unik per bank.
     */
    public function store(StoreBankAccountRequest $request, CreateBankAccount $createBankAccount): BankAccountResource
    {
        /** @status 201 */
        return new BankAccountResource($createBankAccount->handle($request->validated()));
    }

    /**
     * Ubah rekening bank
     *
     * Tidak ada endpoint hapus. Nonaktifkan dengan `is_active=false` agar data transaksi tetap valid.
     */
    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount, UpdateBankAccount $updateBankAccount): BankAccountResource
    {
        return new BankAccountResource($updateBankAccount->handle($bankAccount, $request->validated()));
    }
}
