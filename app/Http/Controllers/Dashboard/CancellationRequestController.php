<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Orders\HandleCancellationRequest;
use App\CancellationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancellationRequestFilterRequest;
use App\Http\Requests\HandleCancellationRequestRequest;
use App\Models\CancellationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CancellationRequestController extends Controller
{
    public function index(CancellationRequestFilterRequest $request): View
    {
        $filters = $request->validated();

        $requests = CancellationRequest::query()
            ->filter($filters)
            ->with(['order.items.vehicle', 'order.transaction', 'user', 'handler'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [CancellationStatus::Pending->value])
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return view('dashboard.cancellation-requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'statuses' => CancellationStatus::cases(),
        ]);
    }

    public function update(HandleCancellationRequestRequest $request, CancellationRequest $cancellationRequest, HandleCancellationRequest $handleCancellationRequest): RedirectResponse
    {
        $handled = $handleCancellationRequest->handle(
            $cancellationRequest,
            $request->user(),
            CancellationStatus::from($request->validated('decision')),
            $request->validated('note'),
        );

        return redirect()
            ->route('dashboard.cancellation-requests.index')
            ->with('status', "Pengajuan pembatalan {$handled->order->code} {$handled->status->label()}.");
    }
}
