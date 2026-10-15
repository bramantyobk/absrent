<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Transactions\VerifyTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionFilterRequest;
use App\Http\Requests\VerifyTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\PaymentProof;
use App\TransactionStatus;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    /**
     * Daftar transaksi
     *
     * Transaksi Menunggu Verifikasi tampil lebih dulu. Filter `search` (kode pesanan, pelanggan) dan `status`.
     * `is_near_expiry` menandai transaksi yang batas verifikasinya hampir habis.
     */
    public function index(TransactionFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return TransactionResource::collection(
            Transaction::query()
                ->filter($filters)
                ->with(['order.user', 'bankAccount'])
                ->orderByRaw('case when status = ? then 0 else 1 end', [TransactionStatus::Verifying->value])
                ->latest('id')
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
        );
    }

    /**
     * Detail transaksi
     */
    public function show(Transaction $transaction): TransactionResource
    {
        Gate::authorize('view', $transaction);

        return new TransactionResource($transaction->load(['order.user', 'bankAccount', 'verifier']));
    }

    /**
     * Verifikasi pembayaran
     *
     * Kirim sebagai `multipart/form-data`. `status=paid` wajib disertai `proof` (JPG/PNG, maksimal 2 MB) dan menjadikan pesanan
     * `booking`. `status=failed` wajib disertai `note`, bersifat final, dan menjadikan pesanan `failed`. `bank_account_id` opsional
     * untuk mengoreksi rekening tujuan. Hanya untuk transaksi Menunggu Verifikasi (422 bila tidak).
     */
    public function verify(VerifyTransactionRequest $request, Transaction $transaction, VerifyTransaction $verifyTransaction): TransactionResource
    {
        return new TransactionResource($verifyTransaction->handle(
            $transaction,
            $request->user(),
            TransactionStatus::from($request->validated('status')),
            $request->file('proof'),
            $request->validated('note'),
            $request->validated('bank_account_id') ? (int) $request->validated('bank_account_id') : null,
        ));
    }

    /**
     * Bukti pembayaran
     *
     * Berkas bukti dari disk privat. 404 bila belum ada bukti.
     */
    public function proof(Transaction $transaction, PaymentProof $paymentProof): StreamedResponse
    {
        return $paymentProof->response($transaction);
    }
}
