<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Order;
use App\Services\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OrderWhatsAppController extends Controller
{
    /**
     * Membuka lagi tautan WhatsApp konfirmasi ke operator yang sama, tanpa mengubah status.
     */
    public function __invoke(Order $order, WhatsAppLink $whatsApp): RedirectResponse
    {
        Gate::authorize('resendConfirmation', $order->loadMissing('transaction'));

        $order->load(['user', 'transaction.bankAccount', 'assignedOperator']);

        $url = $whatsApp->toOperator($order, CompanyProfile::current());

        return $url === null
            ? redirect()->route('orders.show', $order)->with('error', 'Nomor WhatsApp tujuan belum tersedia.')
            : redirect()->away($url);
    }
}
