<?php

namespace App\Http\Controllers;

use App\Actions\Orders\ConfirmOrderPayment;
use App\Http\Requests\ConfirmPaymentRequest;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Order;
use App\Services\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderPaymentController extends Controller
{
    public function show(Order $order): View
    {
        Gate::authorize('pay', $order->loadMissing('transaction'));

        $order->load('items.vehicle');

        return view('orders.payment', [
            'order' => $order,
            'bankAccounts' => BankAccount::query()->active()->ordered()->get(),
        ]);
    }

    /**
     * Konfirmasi pembayaran lalu alihkan ke WhatsApp operator hasil rotator.
     */
    public function store(ConfirmPaymentRequest $request, Order $order, ConfirmOrderPayment $confirmOrderPayment, WhatsAppLink $whatsApp): RedirectResponse
    {
        $order = $confirmOrderPayment->handle($order, (int) $request->validated('bank_account_id'));

        $url = $whatsApp->toOperator($order, CompanyProfile::current());

        return $url === null
            ? redirect()->route('orders.show', $order)->with('status', 'Pembayaran dikonfirmasi. Nomor WhatsApp operator belum tersedia, hubungi kami lewat kontak di bawah.')
            : redirect()->away($url);
    }
}
