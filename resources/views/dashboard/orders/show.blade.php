@extends('layouts.dashboard')

@section('title', "Pesanan {$order->code}")

@section('content')
    @include('dashboard.users._alerts')

    @if ($errors->has('status'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('status') }}</div>
    @endif

    <a href="{{ route('dashboard.orders.index') }}" class="small fw-semibold">&larr; Daftar pesanan</a>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
        <h2 class="card-heading mb-0">{{ $order->code }}</h2>
        <span class="d-flex gap-1">
            <x-status-badge :status="$order->status" />
            @if ($order->transaction)
                <x-status-badge :status="$order->transaction->status" />
            @endif
        </span>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card-custom p-4 mb-4">
                <h3 class="card-heading fs-6">Kendaraan</h3>
                @foreach ($order->items as $item)
                    <div class="d-flex align-items-center gap-3 {{ $loop->first ? '' : 'border-top pt-3 mt-3' }}">
                        <img src="{{ $item->vehicle->photo_url }}" alt="" class="vehicle-thumb" loading="lazy">
                        <div class="flex-grow-1">
                            <a href="{{ route('dashboard.vehicles.show', $item->vehicle) }}" class="fw-semibold text-decoration-none">
                                {{ $item->vehicle->brand }} {{ $item->vehicle->model }}
                            </a>
                            <div class="small text-body-secondary">{{ $item->vehicle->plate_number }}</div>
                            <div class="small">
                                {{ $item->start_at->format('d/m/Y H:i') }} &ndash; {{ $item->end_at->format('d/m/Y H:i') }} {{ config('rental.timezone_label') }}
                                ({{ $item->days }} hari &times; Rp{{ number_format($item->unit_price, 0, ',', '.') }})
                            </div>
                        </div>
                        <strong class="text-nowrap">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</strong>
                    </div>
                @endforeach
            </div>

            <div class="card-custom p-4">
                <h3 class="card-heading fs-6">Pengajuan Pembatalan</h3>
                @forelse ($order->cancellationRequests->sortByDesc('id') as $cancellation)
                    <div class="{{ $loop->first ? '' : 'border-top pt-3 mt-3' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="small text-body-secondary">{{ $cancellation->created_at->format('d/m/Y H:i') }}</span>
                            <span class="fw-semibold">{{ $cancellation->status->label() }}</span>
                        </div>
                        <p class="mb-1">{{ $cancellation->reason }}</p>
                        @if ($cancellation->handled_note)
                            <p class="small text-body-secondary mb-0">
                                {{ $cancellation->handler?->name }}: {{ $cancellation->handled_note }}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-body-secondary mb-0">Belum ada pengajuan pembatalan.</p>
                @endforelse

                @if ($pendingCancellation)
                    <a href="{{ route('dashboard.cancellation-requests.index', ['status' => 'pending']) }}" class="btn btn-outline-primary mt-3">Proses pengajuan</a>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card-custom p-4 mb-4">
                <h3 class="card-heading fs-6">Ringkasan</h3>
                <p class="fs-4 fw-bold mb-3">{{ $order->formattedTotalPrice() }}</p>
                <dl class="small mb-0">
                    <dt>Pelanggan</dt>
                    <dd>{{ $order->user->name }}<br><span class="text-body-secondary">{{ $order->user->phone }}</span></dd>
                    <dt>Dibuat</dt>
                    <dd>{{ $order->created_at->format('d/m/Y H:i') }}</dd>
                    <dt>Operator rotator</dt>
                    <dd>{{ $order->assignedOperator?->name ?? '-' }}</dd>
                    @if ($order->transaction)
                        <dt>Transaksi</dt>
                        <dd><a href="{{ route('dashboard.transactions.show', $order->transaction) }}">{{ $order->transaction->status->label() }}</a></dd>
                    @endif
                </dl>
            </div>

            @if ($nextStatus)
                <div class="card-custom p-4">
                    <h3 class="card-heading fs-6">Ubah status</h3>
                    <form method="POST" action="{{ route('dashboard.orders.status.update', $order) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $nextStatus->value }}">
                        <button type="submit" class="btn btn-primary w-100">Ubah menjadi {{ $nextStatus->label() }}</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
