<?php

namespace App\Http\Controllers\Dashboard;

use App\CancellationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderFilterRequest;
use App\InspectionType;
use App\Models\Order;
use App\OldVehicleStatus;
use App\OrderStatus;
use App\Services\OrderInspectionForm;
use App\Services\ReplacementOptions;
use App\TransactionStatus;
use App\VehicleChangeReason;
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

    public function show(Order $order, OrderInspectionForm $inspectionForm, ReplacementOptions $replacementOptions): View
    {
        Gate::authorize('manage', $order);

        $order->load(['user', 'creator', 'items.vehicle', 'items.inspections.inspector', 'items.vehicleChanges.fromVehicle', 'items.vehicleChanges.toVehicle', 'items.vehicleChanges.changedBy', 'transaction.bankAccount', 'transaction.verifier', 'assignedOperator', 'cancellationRequests.handler']);

        $inspectionType = $inspectionForm->typeFor($order);

        $canChangeVehicle = in_array($order->status, [OrderStatus::Booking, OrderStatus::Rented], true);
        $changes = $order->items->flatMap->vehicleChanges->sortByDesc('id')->values();

        return view('dashboard.orders.show', [
            'canCancelExpired' => $order->status === OrderStatus::Pending && $order->transaction?->status === TransactionStatus::Pending && $order->expires_at?->isPast() === true,
            'canChangeVehicle' => $canChangeVehicle,
            'replacements' => $canChangeVehicle ? $order->items->mapWithKeys(fn ($item): array => [$item->id => $replacementOptions->for($item->setRelation('order', $order))]) : collect(),
            'returnUnits' => $order->status === OrderStatus::Rented ? $inspectionForm->for($order, InspectionType::Return)->keyBy(fn (array $unit): int => $unit['item']->id) : collect(),
            'pickupChecklists' => $inspectionForm->pickupChecklistsByCategory(),
            'changeReasons' => VehicleChangeReason::cases(),
            'changes' => $changes,
            'pendingUnits' => $changes->where('old_vehicle_status', OldVehicleStatus::Pending)->map(fn ($change): array => $inspectionForm->forPendingChange($change))->values(),
            'largeReductionThreshold' => config('rental.vehicle_change_admin_threshold'),
            'order' => $order,
            'inspectionType' => $inspectionType,
            'inspectionUnits' => $inspectionType ? $inspectionForm->for($order, $inspectionType) : collect(),
            'canMarkStolen' => $order->status === OrderStatus::Rented && Gate::allows('markStolen', $order),
            'upcomingForStolen' => $order->status === OrderStatus::Stolen ? $inspectionForm->upcomingForStolen($order) : collect(),
            'pendingCancellation' => $order->cancellationRequests->firstWhere('status', CancellationStatus::Pending),
        ]);
    }
}
