<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Orders\CancelExpiredOrder;
use App\Actions\Orders\ChangeOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeOrderStatusRequest;
use App\Http\Requests\OrderFilterRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /**
     * Daftar pesanan
     *
     * Semua pesanan, terbaru di atas. Filter: `search` (kode, pelanggan, kendaraan), `status`, `type` (kategori kendaraan),
     * `from` dan `to` (rentang tanggal mulai sewa), serta paginasi.
     */
    public function index(OrderFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return OrderResource::collection(
            Order::query()
                ->filter($filters)
                ->with(['user', 'items.vehicle', 'transaction'])
                ->latest('id')
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
        );
    }

    /**
     * Detail pesanan
     */
    public function show(Order $order): OrderResource
    {
        Gate::authorize('manage', $order);

        return new OrderResource($order->load(['user', 'items.vehicle', 'items.inspections', 'items.vehicleChanges.fromVehicle', 'items.vehicleChanges.toVehicle', 'transaction.bankAccount', 'cancellationRequests']));
    }

    /**
     * Ubah status pesanan
     *
     * `booking` menjadi `rented` (inspeksi pengambilan), lalu `rented` menjadi `completed` (inspeksi pengembalian). Keduanya wajib
     * menyertakan `inspections` menurut id item pesanan: `{ "<order_item_id>": { odometer_km, energy_level (0-100), note, equipment: { "<equipment_item_id>": jumlah } } }`.
     * Ceklis `equipment` pengambilan mengikuti perlengkapan aktif sesuai kategori unit, pengembalian mengikuti snapshot pengambilan unit yang sama.
     * Odometer tidak boleh lebih kecil dari odometer terakhir kendaraan (dan odometer pengambilan). `rented` menjadi `stolen` hanya oleh
     * admin dengan `note` wajib, tanpa inspeksi: unit disembunyikan dari katalog. Perubahan lain ditolak (422).
     */
    public function status(ChangeOrderStatusRequest $request, Order $order, ChangeOrderStatus $changeOrderStatus): OrderResource
    {
        return new OrderResource($changeOrderStatus->handle(
            $order,
            OrderStatus::from($request->validated('status')),
            $request->user(),
            $request->inspections(),
            $request->validated('note'),
        ));
    }

    /**
     * Batalkan pesanan yang melewati batas konfirmasi
     *
     * Pembatalan sepihak oleh staf untuk pesanan `pending` yang belum dikonfirmasi pelanggan dan `expires_at`-nya sudah lewat. Pesanan dan
     * transaksi menjadi `cancelled` dan jadwal dilepas. 422 bila batas belum lewat atau pelanggan sudah mengonfirmasi pembayaran.
     */
    public function cancel(Order $order, Request $request, CancelExpiredOrder $cancelExpiredOrder): OrderResource
    {
        Gate::authorize('manage', $order);

        return new OrderResource($cancelExpiredOrder->handle($order, $request->user()));
    }
}
