<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevenueChartRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\OrderStatus;
use App\Services\RevenueReport;
use Illuminate\Http\JsonResponse;

class DashboardChartController extends Controller
{
    /**
     * Status pesanan yang sudah dibayar (dihitung sebagai orderan/pendapatan).
     *
     * @var list<OrderStatus>
     */
    private const PAID_ORDER_STATUSES = [OrderStatus::Booking, OrderStatus::Rented, OrderStatus::Completed];

    public function topVehicles(): JsonResponse
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('vehicles', 'vehicles.id', '=', 'order_items.vehicle_id')
            ->whereIn('orders.status', array_column(self::PAID_ORDER_STATUSES, 'value'))
            ->groupBy('vehicles.brand', 'vehicles.model')
            ->selectRaw('vehicles.brand, vehicles.model, COUNT(*) as total')
            ->orderByDesc('total')
            ->orderBy('vehicles.brand')
            ->orderBy('vehicles.model')
            ->limit(5)
            ->toBase()
            ->get();

        return response()->json([
            'labels' => $rows->map(fn ($row) => $row->brand.' '.$row->model)->all(),
            'data' => $rows->pluck('total')->map(fn ($total) => (int) $total)->all(),
            'total' => (int) $rows->sum('total'),
            'center_label' => 'Orderan',
        ]);
    }

    public function orderStatus(): JsonResponse
    {
        $counts = Order::query()
            ->whereIn('status', array_column(self::PAID_ORDER_STATUSES, 'value'))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $colors = [
            OrderStatus::Booking->value => '#FFC73A',
            OrderStatus::Rented->value => '#9CD323',
            OrderStatus::Completed->value => '#90A3BF',
        ];

        return response()->json([
            'labels' => array_map(fn (OrderStatus $status) => $status->label(), self::PAID_ORDER_STATUSES),
            'data' => array_map(fn (OrderStatus $status) => (int) ($counts[$status->value] ?? 0), self::PAID_ORDER_STATUSES),
            'colors' => array_values($colors),
            'total' => (int) $counts->sum(),
            'center_label' => 'Pesanan',
        ]);
    }

    public function revenue(RevenueChartRequest $request, RevenueReport $revenueReport): JsonResponse
    {
        $days = $revenueReport->daily($request->startDate(), $request->endDate(), $request->vehicleType());
        $sum = (int) $days->sum('total');

        return response()->json([
            'labels' => $days->pluck('label')->all(),
            'data' => $days->pluck('total')->all(),
            'total' => $sum,
            'total_formatted' => 'Rp'.number_format($sum, 0, ',', '.'),
        ]);
    }
}
