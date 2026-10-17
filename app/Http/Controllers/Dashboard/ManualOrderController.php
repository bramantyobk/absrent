<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Orders\CreateManualOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualOrderRequest;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Order;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ManualOrderController extends Controller
{
    public function create(Request $request): View
    {
        Gate::authorize('createManual', Order::class);

        $period = $request->validate([
            'start_at' => ['nullable', 'date', 'after_or_equal:'.now()->startOfMinute()->format('Y-m-d H:i')],
            'days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ], ['start_at.after_or_equal' => 'Waktu mulai tidak boleh di masa lalu.']);

        $hasPeriod = filled($period['start_at'] ?? null) && filled($period['days'] ?? null);
        $company = CompanyProfile::current();

        return view('dashboard.orders.manual', [
            'period' => $period,
            'vehicles' => $hasPeriod
                ? Vehicle::query()->listedInCatalog()->catalogFilter(['start_at' => $period['start_at'], 'days' => $period['days']])->orderBy('brand')->orderBy('model')->orderBy('id')->get()
                : collect(),
            'bankAccounts' => BankAccount::query()->active()->ordered()->get(),
            'isOutsideOfficeHours' => ! $company->isOpenAt(now()),
            'maxVehicles' => config('rental.max_vehicles_per_order'),
            'defaultStart' => now()->addHour()->startOfHour()->format('Y-m-d\TH:i'),
            'timezoneLabel' => config('rental.timezone_label'),
        ]);
    }

    public function store(StoreManualOrderRequest $request, CreateManualOrder $createManualOrder): RedirectResponse
    {
        $order = $createManualOrder->handle($request->user(), $request->orderData(), $request->file('proof'));

        return redirect()
            ->route('dashboard.orders.show', $order)
            ->with('status', "Pesanan manual {$order->code} atas nama {$order->user->name} dibuat dan langsung Lunas.");
    }
}
