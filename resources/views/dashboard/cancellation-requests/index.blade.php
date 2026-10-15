@extends('layouts.dashboard')

@section('title', 'Pengajuan Pembatalan')

@section('content')
    @include('dashboard.users._alerts')

    <div class="card-dash-user p-4">
        <h2 class="card-heading mb-1">Pengajuan Pembatalan</h2>
        <p class="small text-body-secondary mb-4">
            Menyetujui menjadikan pesanan Refund dan melepas jadwal. Dana dikembalikan manual di luar aplikasi, catat infonya pada catatan keputusan.
        </p>

        <form method="GET" action="{{ route('dashboard.cancellation-requests.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-md-6">
                <label for="status" class="visually-hidden">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">Terapkan</button>
                <a href="{{ route('dashboard.cancellation-requests.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        @if ($errors->has('decision') || $errors->has('note'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('decision') ?: $errors->first('note') }}</div>
        @endif

        <div class="d-flex flex-column gap-3">
            @forelse ($requests as $cancellation)
                <article class="border rounded p-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <div>
                            <a href="{{ route('dashboard.orders.show', $cancellation->order) }}" class="fw-semibold">{{ $cancellation->order->code }}</a>
                            <span class="text-body-secondary">&middot; {{ $cancellation->user->name }} ({{ $cancellation->user->phone }})</span>
                        </div>
                        <span class="fw-semibold">{{ $cancellation->status->label() }}</span>
                    </div>
                    <p class="small text-body-secondary mb-1">
                        {{ $cancellation->order->items->map(fn ($item) => $item->vehicle->brand.' '.$item->vehicle->model)->implode(', ') }}
                        &middot; {{ $cancellation->order->formattedTotalPrice() }} &middot; diajukan {{ $cancellation->created_at->format('d/m/Y H:i') }}
                    </p>
                    <p class="mb-2">{{ $cancellation->reason }}</p>

                    @if ($cancellation->status === \App\CancellationStatus::Pending)
                        <form method="POST" action="{{ route('dashboard.cancellation-requests.update', $cancellation) }}" class="row g-2">
                            @csrf
                            @method('PATCH')
                            <div class="col-12">
                                <label for="note_{{ $cancellation->id }}" class="visually-hidden">Catatan keputusan</label>
                                <textarea id="note_{{ $cancellation->id }}" name="note" rows="2" maxlength="1000" class="form-control"
                                    placeholder="Catatan keputusan (info pengembalian dana bila disetujui)" required></textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" name="decision" value="approved" class="btn btn-primary">Setujui</button>
                                <button type="submit" name="decision" value="rejected" class="btn btn-outline-danger">Tolak</button>
                            </div>
                        </form>
                    @else
                        <p class="small text-body-secondary mb-0">
                            {{ $cancellation->handler?->name }}, {{ $cancellation->handled_at?->format('d/m/Y H:i') }}: {{ $cancellation->handled_note }}
                        </p>
                    @endif
                </article>
            @empty
                <p class="text-center text-body-secondary py-5 mb-0">Tidak ada pengajuan pembatalan.</p>
            @endforelse
        </div>

        @if ($requests->hasPages())
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <p class="small text-body-secondary mb-0">
                    Menampilkan {{ $requests->firstItem() }}-{{ $requests->lastItem() }} dari {{ $requests->total() }} pengajuan
                </p>
                {{ $requests->links() }}
            </div>
        @endif
    </div>
@endsection
