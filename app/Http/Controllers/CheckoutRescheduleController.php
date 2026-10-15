<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\CheckoutCart;
use App\Services\RentalQuote;
use Illuminate\Http\RedirectResponse;

/**
 * Ubah jadwal hanya saat pesanan berisi satu kendaraan: item dilepas dan pelanggan kembali ke detail kendaraan
 * dengan jadwal sebelumnya terisi. Dengan dua kendaraan, semuanya harus dihapus dulu.
 */
class CheckoutRescheduleController extends Controller
{
    public function store(CheckoutCart $cart): RedirectResponse
    {
        if (! $cart->canReschedule()) {
            return redirect()
                ->route('checkout.show')
                ->with('error', 'Jadwal hanya dapat diubah bila pesanan berisi satu kendaraan. Hapus semua kendaraan untuk memilih jadwal baru.');
        }

        $previous = $cart->reschedule();

        return redirect()->route('vehicles.show', [
            'vehicle' => Vehicle::query()->findOrFail($previous['vehicle_id']),
            'start_at' => RentalQuote::parse($previous['start_at'])->format('Y-m-d\TH:i'),
            'days' => $previous['days'],
        ]);
    }
}
