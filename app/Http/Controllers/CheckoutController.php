<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrder;
use App\Services\CheckoutCart;
use App\Services\RentalQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(CheckoutCart $cart, RentalQuote $rentalQuote): View
    {
        $lines = $cart->lines();
        $first = $lines->first();

        return view('checkout.show', [
            'lines' => $lines,
            'total' => $lines->sum('subtotal'),
            'hasInvalidLine' => $lines->contains(fn (array $line): bool => $line['error'] !== null),
            'startLabel' => $first ? $rentalQuote->label($first['start_at']) : null,
            'endLabel' => $first ? $rentalQuote->label($first['end_at']) : null,
            'days' => $first['days'] ?? null,
            'addVehicleUrl' => $first ? route('vehicles.index', ['start_at' => $first['start_at']->format('Y-m-d\TH:i'), 'days' => $first['days']]) : route('vehicles.index'),
            'canAddMore' => $cart->canAddMore(),
            'canReschedule' => $cart->canReschedule(),
            'maxVehicles' => config('rental.max_vehicles_per_order'),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, CheckoutCart $cart, CreateOrder $createOrder): RedirectResponse
    {
        $period = $cart->period();

        if ($cart->isEmpty() || $period === null) {
            return redirect()->route('checkout.show')->with('error', 'Pilih kendaraan dan jadwal terlebih dahulu.');
        }

        $order = $createOrder->handle(
            $request->user(),
            $period['start_at'],
            $period['days'],
            $cart->vehicleIds(),
            $request->string('idempotency_key')->limit(100, '')->toString() ?: null,
        );

        $cart->clear();

        return redirect()
            ->route('orders.payment', $order)
            ->with('status', "Pesanan {$order->code} berhasil dibuat. Lanjutkan dengan pembayaran.");
    }
}
