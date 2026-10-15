@extends('layouts.app')

@section('title', 'Pesanan Saya')

@section('content')
    <h1 class="page-title mb-3">Pesanan saya</h1>

    <div class="d-flex flex-column gap-3">
        @forelse ($orders as $order)
            @php($first = $order->items->first())
            <a href="{{ route('orders.show', $order) }}" class="order-card">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <span class="fw-semibold">{{ $order->code }}</span>
                    <span class="d-flex gap-1">
                        <x-status-badge :status="$order->status" />
                        @if ($order->transaction)
                            <x-status-badge :status="$order->transaction->status" />
                        @endif
                    </span>
                </div>
                <p class="mb-1">
                    {{ $first?->vehicle->brand }} {{ $first?->vehicle->model }}
                    @if ($order->items->count() > 1)
                        <span class="text-body-secondary">(+{{ $order->items->count() - 1 }} lainnya)</span>
                    @endif
                </p>
                <div class="d-flex flex-wrap justify-content-between gap-2 small text-body-secondary">
                    <span>{{ $order->created_at->format('d/m/Y H:i') }}</span>
                    <strong class="text-body">{{ $order->formattedTotalPrice() }}</strong>
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
