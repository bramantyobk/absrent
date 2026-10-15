<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Orders\ChangeOrderVehicle;
use App\Actions\Orders\ResolveOldVehicle;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeOrderVehicleRequest;
use App\Http\Requests\ResolveOldVehicleRequest;
use App\Http\Resources\OrderVehicleChangeResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderVehicleChange;
use App\OldVehicleStatus;
use App\OrderStatus;
use App\Services\ReplacementOptions;
use App\VehicleChangeReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderVehicleChangeController extends Controller
{
    /**
     * Unit pengganti
     *
     * Unit yang kosong pada sisa rentang item (dari sekarang untuk pesanan `rented`, seluruh rentang untuk `booking`, termasuk jeda),
     * tampil di katalog, beserta `suggested_amount` (hari × selisih tarif harian, positif tambah bayar, negatif refund). Filter `reason`
     * membatasi kategori: selain `customer_request`, hanya unit sekategori.
     */
    public function options(Order $order, OrderItem $item, ReplacementOptions $replacements): JsonResponse
    {
        Gate::authorize('manage', $order);
        abort_unless($item->order_id === $order->id, 404);
        abort_unless(in_array($order->status, [OrderStatus::Booking, OrderStatus::Rented], true), 422, 'Kendaraan hanya dapat diganti pada pesanan Booking atau Disewa.');

        $reason = VehicleChangeReason::tryFrom((string) request()->query('reason'));
        $item->load('vehicle');

        $options = $replacements->for($item)
            ->when($reason?->requiresSameCategory(), fn ($vehicles) => $vehicles->where('type', $item->vehicle->type))
            ->values()
            ->map(fn ($vehicle): array => [
                'id' => $vehicle->id,
                'name' => "{$vehicle->brand} {$vehicle->model}",
                'plate_number' => $vehicle->plate_number,
                'type' => $vehicle->type->value,
                'price_per_day' => $vehicle->price_per_day,
                'odometer_km' => $vehicle->odometer_km,
                'suggested_amount' => $replacements->suggestedAmount($item, $vehicle),
            ]);

        return response()->json(['data' => $options]);
    }

    /**
     * Ganti kendaraan
     *
     * Hanya pesanan `booking` atau `rented`. Body: `vehicle_id`, `reason` (`customer_request`, `accident`, `vehicle_damaged`, `vehicle_late_return`, `other`),
     * `note`, dan `final_amount` (bertanda: positif tambah bayar, negatif pengurangan, dibayar manual). Pengurangan Rp100.000 atau lebih hanya admin (403).
     * Dari `rented` wajib `old_vehicle` (`status`: `returned` dengan `inspection`, `pending` atau `lost` dengan `note`) dan `pickup` (inspeksi unit baru).
     * Ceklis `equipment` dikirim menurut id perlengkapan.
     */
    public function store(ChangeOrderVehicleRequest $request, Order $order, OrderItem $item, ChangeOrderVehicle $changeOrderVehicle): JsonResponse
    {
        abort_unless($item->order_id === $order->id, 404);

        $change = $changeOrderVehicle->handle($order, $item, $request->user(), $request->changeData());

        return (new OrderVehicleChangeResource($change))->response()->setStatusCode(201);
    }

    /**
     * Selesaikan unit lama yang ditunda
     *
     * `action=returned` dengan `inspection` (unit langsung bebas) atau `action=lost` dengan `note` (unit disembunyikan dari katalog).
     */
    public function oldVehicle(ResolveOldVehicleRequest $request, OrderVehicleChange $change, ResolveOldVehicle $resolveOldVehicle): OrderVehicleChangeResource
    {
        return new OrderVehicleChangeResource($resolveOldVehicle->handle(
            $change,
            $request->user(),
            OldVehicleStatus::from($request->validated('action')),
            $request->validated('inspection') ?? [],
            $request->validated('note'),
        ));
    }
}
