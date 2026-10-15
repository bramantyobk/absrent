<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Order;
use App\Services\WhatsAppLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('orders.index', [
            'orders' => $request->user()->orders()
                ->with(['items.vehicle', 'transaction'])
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function show(Order $order, WhatsAppLink $whatsApp): View
    {
        Gate::authorize('view', $order);

        $order->load(['items.vehicle', 'transaction', 'cancellationRequests']);

        return view('orders.show', [
            'order' => $order,
            'supportUrl' => $whatsApp->toSupport(CompanyProfile::current(), "Halo, saya butuh bantuan untuk pesanan {$order->code}."),
        ]);
    }
}
