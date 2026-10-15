<?php

namespace App\Http\Controllers\Dashboard;

use App\CancellationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderFilterRequest;
use App\Models\Order;
use App\OrderStatus;
use App\VehicleType;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(OrderFilterRequest $request): View
    {
        $filters = $request->validated();

        $orders = Order::query()
            ->filter($filters)
            ->with(['user', 'items.vehicle', 'transaction'])
            ->withExists(['cancellationRequests as has_pending_cancellation' => fn ($query) => $query->where('status', CancellationStatus::Pending)])
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return view('dashboard.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => OrderStatus::cases(),
            'types' => VehicleType::cases(),
        ]);
    }

    public function show(Order $order): View
    {
        Gate::authorize('manage', $order);

        $order->load(['user', 'items.vehicle', 'transaction.bankAccount', 'transaction.verifier', 'assignedOperator', 'cancellationRequests.handler']);

        return view('dashboard.orders.show', [
            'order' => $order,
            'nextStatus' => match ($order->status) {
                OrderStatus::Booking => OrderStatus::Rented,
                OrderStatus::Rented => OrderStatus::Completed,
                default => null,
            },
            'pendingCancellation' => $order->cancellationRequests->firstWhere('status', CancellationStatus::Pending),
        ]);
    }
}
