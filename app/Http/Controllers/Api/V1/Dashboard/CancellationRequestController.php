<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Orders\HandleCancellationRequest;
use App\CancellationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancellationRequestFilterRequest;
use App\Http\Requests\HandleCancellationRequestRequest;
use App\Http\Resources\CancellationRequestResource;
use App\Models\CancellationRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CancellationRequestController extends Controller
{
    /**
     * Daftar pengajuan pembatalan
     *
     * Pengajuan yang menunggu tampil lebih dulu. Filter `status`.
     */
    public function index(CancellationRequestFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return CancellationRequestResource::collection(
            CancellationRequest::query()
                ->filter($filters)
                ->with(['order', 'user', 'handler'])
                ->orderByRaw('case when status = ? then 0 else 1 end', [CancellationStatus::Pending->value])
                ->latest('id')
                ->paginate($filters['per_page'] ?? 10)
                ->withQueryString()
        );
    }

    /**
     * Putuskan pengajuan pembatalan
     *
     * `decision` berisi `approved` atau `rejected`, `note` wajib. Persetujuan menjadikan pesanan `refunded` dan transaksi
     * `cancelled` (jadwal dilepas), hanya selama pesanan masih `booking`. Dana dikembalikan manual di luar aplikasi.
     */
    public function update(HandleCancellationRequestRequest $request, CancellationRequest $cancellationRequest, HandleCancellationRequest $handleCancellationRequest): CancellationRequestResource
    {
        return new CancellationRequestResource($handleCancellationRequest->handle(
            $cancellationRequest,
            $request->user(),
            CancellationStatus::from($request->validated('decision')),
            $request->validated('note'),
        ));
    }
}
