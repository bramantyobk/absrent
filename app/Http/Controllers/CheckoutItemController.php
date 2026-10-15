<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentalPeriodRequest;
use App\Models\Vehicle;
use App\Services\CheckoutCart;
use Illuminate\Http\RedirectResponse;

/**
 * Item pada ringkasan pesanan (keranjang di sesi): tambah kendaraan beserta periodenya, atau hapus.
 */
class CheckoutItemController extends Controller
{
    public function store(RentalPeriodRequest $request, Vehicle $vehicle, CheckoutCart $cart): RedirectResponse
    {
        abort_unless($vehicle->is_available, 404);

        $cart->add($vehicle, $request->validated('start_at'), (int) $request->validated('days'));

        return redirect()
            ->route('checkout.show')
            ->with('status', "{$vehicle->brand} {$vehicle->model} ditambahkan ke pesanan.");
    }

    public function destroy(int $index, CheckoutCart $cart): RedirectResponse
    {
        $cart->remove($index);

        return redirect()->route('checkout.show')->with('status', 'Kendaraan dihapus dari pesanan.');
    }
}
