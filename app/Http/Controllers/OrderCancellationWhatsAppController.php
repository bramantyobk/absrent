<?php

namespace App\Http\Controllers;

use App\CancellationStatus;
use App\Models\CompanyProfile;
use App\Models\Order;
use App\Services\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OrderCancellationWhatsAppController extends Controller
{
    /**
     * Membuka lagi WhatsApp untuk pengajuan pembatalan yang masih menunggu, tanpa membuat pengajuan baru.
     */
    public function __invoke(Order $order, WhatsAppLink $whatsApp): RedirectResponse
    {
        Gate::authorize('view', $order);

        $request = $order->cancellationRequests()->where('status', CancellationStatus::Pending)->latest('id')->firstOrFail();

        $url = $whatsApp->toCancellation($order->load('assignedOperator'), CompanyProfile::current(), $request->reason);

        return $url === null
            ? redirect()->route('orders.show', $order)->with('error', 'Nomor WhatsApp tujuan belum tersedia.')
            : redirect()->away($url);
    }
}
