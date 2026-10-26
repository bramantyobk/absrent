@extends('layouts.app')

@section('title', 'Pesanan Saya')

@section('content')
    <h1 class="page-title mb-3">Pesanan saya</h1>

    <div class="d-flex flex-column gap-3">
        @forelse ($orders as $order)
            @php($first = $order->items->first())
            <a href="{{ route('orders.show', $order) }}" class="order-card">
                @if ($first)
                    <img src="{{ $first->vehicle->photo_url }}" alt="" class="order-card-photo" loading="lazy">
                @endif
                <div class="order-card-main">
                    <span class="fw-semibold">{{ $order->code }}</span>
                    <p class="mb-2">
                        {{ $first?->vehicle->brand }} {{ $first?->vehicle->model }}
                        @if ($order->items->count() > 1)
                            <span class="text-body-secondary">(+{{ $order->items->count() - 1 }} lainnya)</span>
                        @endif
                        @if ($first)
                            <span class="vehicle-chip ms-1">{{ $first->days }} hari</span>
                        @endif
                    </p>
                    @if ($first)
                        <dl class="order-meta order-meta-split small mb-2">
                            <div>
                                <dt>Mulai</dt>
                                <dd>{{ $first->start_at->toLongIndonesian() }}</dd>
                            </div>
                            <div>
                                <dt>Kembali</dt>
                                <dd>{{ $first->end_at->toLongIndonesian() }}</dd>
                            </div>
                        </dl>
                    @endif
                    <p class="small text-body-secondary mb-0">Dibuat {{ $order->created_at->toLongIndonesian() }}</p>
                </div>
                <div class="order-card-side">
                    <strong class="order-card-total">{{ $order->formattedTotalPrice() }}</strong>
                    <div class="order-status">
                        <span class="order-status-item">
                            <span class="order-status-label">Status pesanan</span>
                            <x-status-badge :status="$order->status" />
                        </span>
                        @if ($order->transaction)
                            <span class="order-status-item">
                                <span class="order-status-label">Pembayaran</span>
                                <x-status-badge :status="$order->transaction->status" />
                            </span>
                        @endif
                    </div>
                    <span class="order-card-link">Lihat detail <i class="bi bi-chevron-right" aria-hidden="true"></i></span>
                </div>
            </a>
        @empty
            <div class="empty-state">
                <p class="mb-3">Anda belum memiliki pesanan.</p>
                <a href="{{ route('vehicles.index') }}" class="btn btn-primary">Pilih kendaraan</a>
            </div>
        @endforelse
    </div>

    @if ($orders->hasPages())
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
@endsection
