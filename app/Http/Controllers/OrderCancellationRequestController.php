<?php

namespace App\Http\Controllers;

use App\Actions\Orders\RequestOrderCancellation;
use App\Http\Requests\StoreCancellationRequestRequest;
use App\Models\CompanyProfile;
use App\Models\Order;
use App\Services\WhatsAppLink;
use Illuminate\Http\RedirectResponse;

class OrderCancellationRequestController extends Controller
{
    /**
     * Mencatat pengajuan lalu mengarahkan pelanggan ke WhatsApp operator dengan pesan berisi kode dan alasan.
     */
    public function store(StoreCancellationRequestRequest $request, Order $order, RequestOrderCancellation $requestOrderCancellation, WhatsAppLink $whatsApp): RedirectResponse
    {
        $requestOrderCancellation->handle($order, $request->user(), $request->validated('reason'));

        $url = $whatsApp->toCancellation($order->load('assignedOperator'), CompanyProfile::current(), $request->validated('reason'));

        return $url === null
            ? redirect()->route('orders.show', $order)->with('status', 'Pengajuan pembatalan tercatat, tetapi nomor WhatsApp tujuan belum tersedia. Operator akan menghubungi Anda.')
            : redirect()->away($url);
    }
}
