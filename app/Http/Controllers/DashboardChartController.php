<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevenueChartRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\OrderStatus;
use App\TransactionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

    public function revenue(RevenueChartRequest $request): JsonResponse
    {
        $start = $request->startDate();
        $end = $request->endDate();

        $totals = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('transactions', 'transactions.order_id', '=', 'orders.id')
            ->join('vehicles', 'vehicles.id', '=', 'order_items.vehicle_id')
            ->where('transactions.status', TransactionStatus::Paid->value)
            ->whereIn('orders.status', array_column(self::PAID_ORDER_STATUSES, 'value'))
            ->whereBetween('transactions.verified_at', [$start, $end])
            ->when($request->vehicleType(), fn ($query, $type) => $query->where('vehicles.type', $type->value))
            ->groupBy(DB::raw('DATE(transactions.verified_at)'))
            ->selectRaw('DATE(transactions.verified_at) as day, SUM(order_items.subtotal) as total')
            ->toBase()
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        foreach ($start->toPeriod($end) as $day) {
            $labels[] = $day->format('d/m');
            $data[] = (int) ($totals[$day->toDateString()] ?? 0);
        }

        $sum = array_sum($data);

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'total' => $sum,
            'total_formatted' => 'Rp'.number_format($sum, 0, ',', '.'),
        ]);
    }
}
