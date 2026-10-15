@extends('layouts.dashboard')

@section('title', "Pesanan {$order->code}")

@section('content')
    @php($autoOpen = $errors->isNotEmpty() ? (old('status') === 'stolen' ? 'stolenModal' : (old('status') ? 'statusModal' : null)) : null)
    <div data-order-inspection @if ($autoOpen) data-auto-open="{{ $autoOpen }}" @endif></div>

    @include('dashboard.users._alerts')

    @if ($errors->has('status') || $errors->has('inspections'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('status') ?: $errors->first('inspections') }}</div>
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

            @if ($order->status === \App\OrderStatus::Stolen)
                <div class="card-custom p-4 mb-4 border-danger">
                    <h3 class="card-heading fs-6">Pesanan Dicuri</h3>
                    <p class="mb-2">{{ $order->status_note }}</p>
                    <p class="small text-body-secondary">Unit disembunyikan dari katalog sampai ditangani admin. Pesanan mendatang atas unit ini perlu diganti kendaraannya:</p>
                    @forelse ($upcomingForStolen as $upcoming)
                        <div class="small border-top pt-2 mt-2">
                            <a href="{{ route('dashboard.orders.show', $upcoming->order) }}" class="fw-semibold">{{ $upcoming->order->code }}</a>
                            &middot; {{ $upcoming->vehicle->brand }} {{ $upcoming->vehicle->model }}
                            &middot; {{ $upcoming->start_at->format('d/m/Y H:i') }} &middot; {{ $upcoming->order->user->name }}
                        </div>
                    @empty
                        <p class="small mb-0">Tidak ada pesanan mendatang atas unit ini.</p>
                    @endforelse
                </div>
            @endif

            <div class="card-custom p-4 mb-4">
                <h3 class="card-heading fs-6">Inspeksi</h3>
                @php($hasInspection = $order->items->contains(fn ($item) => $item->inspections->isNotEmpty()))
                @forelse ($order->items as $item)
                    @foreach ($item->inspections->sortBy('id') as $inspection)
                        <div class="{{ $loop->parent->first && $loop->first ? '' : 'border-top pt-3 mt-3' }}">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <span class="fw-semibold">{{ $inspection->type->label() }} &middot; {{ $item->vehicle->brand }} {{ $item->vehicle->model }}</span>
                                <span class="small text-body-secondary">{{ $inspection->inspected_at->format('d/m/Y H:i') }} &middot; {{ $inspection->inspector?->name }}</span>
                            </div>
                            <div class="small">
                                Odometer {{ number_format($inspection->odometer_km, 0, ',', '.') }} km
                                @if ($inspection->energy_level !== null)
                                    &middot; {{ $item->vehicle->drive_type?->energyLabel() ?? 'Bensin' }} {{ $inspection->energy_level }}%
                                @endif
                            </div>
                            @if ($inspection->equipment_checks)
                                <div class="small text-body-secondary">
                                    Perlengkapan:
                                    {{ collect($inspection->equipment_checks)->map(fn ($row) => $row['name'].' '.$row['checked'].'/'.$row['expected'])->implode(', ') }}
                                </div>
                            @endif
                            @if ($inspection->note)
                                <div class="small text-body-secondary">{{ $inspection->note }}</div>
                            @endif
                        </div>
                    @endforeach
                @empty
                @endforelse
                @unless ($hasInspection)
                    <p class="text-body-secondary mb-0">Belum ada inspeksi.</p>
                @endunless
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

            @if ($inspectionType)
                <div class="card-custom p-4 mb-4">
                    <h3 class="card-heading fs-6">Ubah status</h3>
                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#statusModal">
                        {{ $inspectionType === \App\InspectionType::Pickup ? 'Pengambilan: ubah menjadi Disewa' : 'Pengembalian: ubah menjadi Selesai' }}
                    </button>
                    <p class="form-text mb-0">Isi inspeksi tiap unit. Status dan inspeksi disimpan bersamaan.</p>
                </div>
            @endif

            @if ($canMarkStolen)
                <div class="card-custom p-4">
                    <h3 class="card-heading fs-6">Unit dicuri atau tidak dikembalikan</h3>
                    <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#stolenModal">Tandai Dicuri</button>
                    <p class="form-text mb-0">Hanya admin. Pesanan selesai tanpa inspeksi dan unit disembunyikan dari katalog.</p>
                </div>
            @endif
        </div>
    </div>

    @if ($inspectionType)
        @include('dashboard.orders._status-modal')
    @endif

    @if ($canMarkStolen)
        @include('dashboard.orders._stolen-modal')
    @endif
@endsection
