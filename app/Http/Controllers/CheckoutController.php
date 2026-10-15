<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrder;
use App\Http\Requests\RentalPeriodRequest;
use App\Models\Vehicle;
use App\Services\CheckoutCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(CheckoutCart $cart): View
    {
        $lines = $cart->lines();

        return view('checkout.show', [
            'lines' => $lines,
            'total' => $lines->sum('subtotal'),
            'hasInvalidLine' => $lines->contains(fn (array $line): bool => $line['error'] !== null),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function addItem(RentalPeriodRequest $request, Vehicle $vehicle, CheckoutCart $cart): RedirectResponse
    {
        abort_unless($vehicle->is_available, 404);

        $cart->add($vehicle, $request->validated('start_at'), $request->validated('end_at'));

        return redirect()
            ->route('checkout.show')
            ->with('status', "{$vehicle->brand} {$vehicle->model} ditambahkan ke pesanan.");
    }

    public function removeItem(int $index, CheckoutCart $cart): RedirectResponse
    {
        $cart->remove($index);

        return redirect()->route('checkout.show')->with('status', 'Kendaraan dihapus dari pesanan.');
    }

    public function store(Request $request, CheckoutCart $cart, CreateOrder $createOrder): RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('checkout.show')->with('error', 'Pilih kendaraan dan jadwal terlebih dahulu.');
        }

        $order = $createOrder->handle($request->user(), $cart->items(), $request->string('idempotency_key')->limit(100, '')->toString() ?: null);

        $cart->clear();

        return redirect()
            ->route('orders.show', $order)
            ->with('status', "Pesanan {$order->code} berhasil dibuat.");
    }
}
