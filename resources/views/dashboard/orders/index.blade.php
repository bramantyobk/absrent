@extends('layouts.dashboard')

@section('title', 'Daftar Pesanan')

@section('content')
    @include('dashboard.users._alerts')

    <div class="card-dash-user p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <h2 class="card-heading mb-0">Daftar Pesanan</h2>
            <a href="{{ route('dashboard.orders.manual.create') }}" class="btn btn-primary">Buat pesanan manual</a>
        </div>

        <form method="GET" action="{{ route('dashboard.orders.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-xl-4">
                <label for="search" class="visually-hidden">Cari</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                    class="form-control" placeholder="Cari kode, pelanggan, atau kendaraan">
            </div>
            <div class="col-6 col-xl-2">
                <label for="status" class="visually-hidden">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-xl-2">
                <label for="type" class="visually-hidden">Kategori</label>
                <select id="type" name="type" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-xl-2">
                <label for="from" class="visually-hidden">Mulai sewa dari</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control @error('from') is-invalid @enderror" title="Mulai sewa dari">
            </div>
            <div class="col-6 col-xl-2">
                <label for="to" class="visually-hidden">Mulai sewa sampai</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control @error('to') is-invalid @enderror" title="Mulai sewa sampai">
                @error('to')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">Terapkan</button>
                <a href="{{ route('dashboard.orders.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table">
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Kendaraan</th>
                        <th scope="col">Periode</th>
                        <th scope="col">Total</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php($first = $order->items->first())
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $order->code }}</td>
                            <td>{{ $order->user->name }}</td>
                            <td>
                                {{ $first?->vehicle->brand }} {{ $first?->vehicle->model }}
                                @if ($order->items->count() > 1)
                                    <span class="text-body-secondary">(+{{ $order->items->count() - 1 }})</span>
                                @endif
                            </td>
                            <td class="small text-nowrap">
                                {{ $first?->start_at->format('d/m/Y H:i') }}<br>{{ $first?->end_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="text-nowrap">{{ $order->formattedTotalPrice() }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <x-status-badge :status="$order->status" />
                                    @if ($order->transaction && $order->status === \App\OrderStatus::Pending)
                                        <x-status-badge :status="$order->transaction->status" />
                                    @endif
                                    @if ($order->status === \App\OrderStatus::Rented && $order->has_overdue_item)
                                        <span class="status-badge status-batal">Terlambat</span>
                                    @endif
                                    @if ($order->isManual())
                                        <span class="status-badge status-returned">Manual</span>
                                    @endif
                                    @if ($order->has_pending_cancellation)
                                        <span class="status-badge status-refund">Ajuan batal</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('dashboard.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-5">Tidak ada pesanan yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <p class="small text-body-secondary mb-0">
                    Menampilkan {{ $orders->firstItem() }}-{{ $orders->lastItem() }} dari {{ $orders->total() }} pesanan
                </p>
                {{ $orders->links() }}
            </div>
        @endif
    </div>
@endsection
