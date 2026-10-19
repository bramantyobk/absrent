<?php

namespace App\Http\Controllers;

use App\CancellationStatus;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\OrderStatus;
use App\TransactionStatus;
use App\VehicleType;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'vehicleStats' => $this->vehicleStats(),
            'recentOrders' => Order::query()
                ->with(['items.vehicle'])
                ->latest()
                ->limit(8)
                ->get(),
            'verification' => [
                'waiting' => Transaction::query()->where('status', TransactionStatus::Verifying)->count(),
                'nearExpiry' => Transaction::query()->nearExpiry()->count(),
                'overdueRentals' => Order::query()->overdue()->count(),
                'pendingCancellations' => CancellationRequest::query()->where('status', CancellationStatus::Pending)->count(),
            ],
            'vehicleTypes' => VehicleType::cases(),
            'defaultStartDate' => now()->subDays(29)->toDateString(),
            'defaultEndDate' => now()->toDateString(),
        ]);
    }

    /**
     * @return array{total: int, available: int, booked: int, rented: int}
     */
    private function vehicleStats(): array
    {
        $withStatus = fn (OrderStatus $status) => fn ($query) => $query
            ->whereHas('order', fn ($order) => $order->where('status', $status));

        return [
            'total' => Vehicle::count(),
            'available' => Vehicle::query()->availableAt(now())->count(),
            'booked' => Vehicle::query()->whereHas('orderItems', $withStatus(OrderStatus::Booking))->count(),
            'rented' => Vehicle::query()->whereHas('orderItems', $withStatus(OrderStatus::Rented))->count(),
        ];
    }
}
