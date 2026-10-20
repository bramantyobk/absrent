<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrder;
use App\Models\CompanyProfile;
use App\Services\CheckoutCart;
use App\Services\TermsTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutTermsController extends Controller
{
    /**
     * Syarat dan ketentuan sebelum pesanan dibuat. Pesanan baru dibuat saat pelanggan menyetujui (`checkout.store`), sehingga
     * pesanan Pending tidak menahan jadwal selama pelanggan membaca. Pengecekan awal di sini hanya agar pelanggan tidak
     * membaca dulu baru ditolak.
     */
    public function show(Request $request, CheckoutCart $cart, CreateOrder $createOrder, TermsTemplate $termsTemplate): View|RedirectResponse
    {
        $lines = $cart->lines();
        $first = $lines->first();

        if ($first === null) {
            return redirect()->route('checkout.show')->with('error', 'Pilih kendaraan dan jadwal terlebih dahulu.');
        }

        if ($lines->contains(fn (array $line): bool => $line['error'] !== null)) {
            return redirect()->route('checkout.show')->with('error', 'Hapus item yang tidak valid sebelum membuat pesanan.');
        }

        if ($message = $createOrder->pendingLimitMessage($request->user())) {
            return redirect()->route('checkout.show')->with('error', $message);
        }

        $terms = $termsTemplate->forOrder(
            CompanyProfile::current(),
            $request->user(),
            $lines->pluck('vehicle'),
            $first['start_at'],
            $first['days'],
            $lines->sum('subtotal'),
        );

        return view('checkout.terms', [
            'blocks' => $terms['blocks'],
            'idempotencyKey' => $request->old('idempotency_key', (string) Str::uuid()),
        ]);
    }
}
