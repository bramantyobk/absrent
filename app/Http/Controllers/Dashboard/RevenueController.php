<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevenueChartRequest;
use App\Services\RevenueReport;
use App\VehicleType;
use Illuminate\View\View;

class RevenueController extends Controller
{
    public function index(RevenueChartRequest $request, RevenueReport $revenueReport): View
    {
        $start = $request->startDate();
        $end = $request->endDate();
        $type = $request->vehicleType();

        $days = $revenueReport->daily($start, $end, $type);

        return view('dashboard.revenue.index', [
            'filters' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'type' => $type?->value],
            'types' => VehicleType::cases(),
            'summary' => ['base' => (int) $days->sum('base'), 'delta' => (int) $days->sum('delta'), 'total' => (int) $days->sum('total')],
            'days' => $days->filter(fn (array $day): bool => $day['base'] !== 0 || $day['delta'] !== 0)->reverse()->values(),
            'vehicles' => $revenueReport->byVehicle($start, $end, $type),
            'changes' => $revenueReport->changes($start, $end, $type),
        ]);
    }
}
