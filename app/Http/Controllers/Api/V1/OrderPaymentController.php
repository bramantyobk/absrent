<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrderPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPaymentRequest;
use App\Http\Resources\BankAccountResource;
use App\Http\Resources\OrderResource;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Order;
use App\Services\WhatsAppLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderPaymentController extends Controller
{
    /**
     * Info pembayaran
     *
     * Total pesanan dan rekening tujuan yang aktif (berurutan). Hanya untuk pesanan `Pending` yang belum dikonfirmasi,
     * selain itu 403. Pesanan milik pelanggan lain menghasilkan 404.
     */
    public function show(Order $order): OrderResource
    {
        Gate::authorize('pay', $order->loadMissing('transaction'));

        return (new OrderResource($order->load(['items.vehicle', 'transaction.bankAccount'])))
            ->additional(['bank_accounts' => BankAccountResource::collection(BankAccount::query()->active()->ordered()->get())]);
    }

    /**
     * Konfirmasi pembayaran
     *
     * Hanya dari `Pending` dan hanya sekali. Menyimpan rekening tujuan (`bank_account_id`, harus aktif), menetapkan operator
     * lewat rotator (jatuh ke nomor support bila tidak ada operator aktif), mengubah transaksi menjadi `Menunggu Verifikasi`,
     * dan memulai batas verifikasi. Respons memuat `whatsapp_url` untuk membuka WhatsApp dengan pesan konfirmasi.
     */
    public function confirm(ConfirmPaymentRequest $request, Order $order, ConfirmOrderPayment $confirmOrderPayment, WhatsAppLink $whatsApp): OrderResource
    {
        $order = $confirmOrderPayment->handle($order, (int) $request->validated('bank_account_id'));

        return (new OrderResource($order))
            ->additional(['whatsapp_url' => $whatsApp->toOperator($order, CompanyProfile::current())]);
    }

    /**
     * Tautan WhatsApp konfirmasi
     *
     * Tautan yang sama dengan hasil konfirmasi (operator yang sama), tanpa mengubah status. Hanya saat `Menunggu Verifikasi`.
     */
    public function whatsapp(Order $order, WhatsAppLink $whatsApp): JsonResponse
    {
        Gate::authorize('resendConfirmation', $order->loadMissing('transaction'));

        $order->load(['user', 'transaction.bankAccount', 'assignedOperator']);

        return response()->json(['data' => ['whatsapp_url' => $whatsApp->toOperator($order, CompanyProfile::current())]]);
    }

    /**
     * Batalkan pesanan
     *
     * Hanya untuk pesanan `Pending` yang belum dikonfirmasi. Pesanan dan transaksi menjadi `Dibatalkan` dan jadwal dilepas.
     */
    public function cancel(Order $order, CancelOrder $cancelOrder): OrderResource
    {
        Gate::authorize('pay', $order->loadMissing('transaction'));

        return new OrderResource($cancelOrder->handle($order));
    }
}
