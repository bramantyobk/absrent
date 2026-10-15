<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Transactions\VerifyTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionFilterRequest;
use App\Http\Requests\VerifyTransactionRequest;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(TransactionFilterRequest $request): View
    {
        $filters = $request->validated();

        $transactions = Transaction::query()
            ->filter($filters)
            ->with(['order.user', 'order.items.vehicle', 'bankAccount'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [TransactionStatus::Verifying->value])
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return view('dashboard.transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'statuses' => TransactionStatus::cases(),
        ]);
    }

    public function show(Transaction $transaction): View
    {
        Gate::authorize('view', $transaction);

        $transaction->load(['order.user', 'order.items.vehicle', 'bankAccount', 'verifier']);

        return view('dashboard.transactions.show', [
            'transaction' => $transaction,
            'bankAccounts' => BankAccount::query()->ordered()->get(),
            'canVerify' => $transaction->status === TransactionStatus::Verifying,
        ]);
    }

    public function verify(VerifyTransactionRequest $request, Transaction $transaction, VerifyTransaction $verifyTransaction): RedirectResponse
    {
        $transaction = $verifyTransaction->handle(
            $transaction,
            $request->user(),
            TransactionStatus::from($request->validated('status')),
            $request->file('proof'),
            $request->validated('note'),
            $request->validated('bank_account_id') ? (int) $request->validated('bank_account_id') : null,
        );

        return redirect()
            ->route('dashboard.transactions.show', $transaction)
            ->with('status', "Transaksi {$transaction->order->code} ditetapkan {$transaction->status->label()}.");
    }
}
