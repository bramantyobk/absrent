@extends('layouts.app')

@section('title', "Pesanan {$order->code}")

@section('content')
    <a href="{{ route('orders.index') }}" class="small fw-semibold">&larr; Pesanan saya</a>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
        <h1 class="page-title mb-0">{{ $order->code }}</h1>
        <span class="d-flex gap-1">
            <x-status-badge :status="$order->status" />
            @if ($order->transaction)
                <x-status-badge :status="$order->transaction->status" />
            @endif
        </span>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="d-flex flex-column gap-3">
                @foreach ($order->items as $item)
                    <article class="order-line">
                        <img src="{{ $item->vehicle->photo_url }}" alt="" class="order-line-photo">
                        <div class="flex-grow-1">
                            <h2 class="order-line-title">{{ $item->vehicle->brand }} {{ $item->vehicle->model }}</h2>
                            <p class="small text-body-secondary mb-1">
                                {{ $item->start_at->format('d/m/Y H:i') }} &ndash; {{ $item->end_at->format('d/m/Y H:i') }} {{ config('rental.timezone_label') }}
                            </p>
                            <p class="small mb-0">
                                {{ $item->days }} hari &times; Rp{{ number_format($item->unit_price, 0, ',', '.') }}
                                = <strong>Rp{{ number_format($item->subtotal, 0, ',', '.') }}</strong>
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="summary-card">
                <h2 class="section-title fs-6">Total</h2>
                <p class="summary-total">{{ $order->formattedTotalPrice() }}</p>
                <dl class="small mb-3">
                    <dt>Dibuat</dt>
                    <dd>{{ $order->created_at->format('d/m/Y H:i') }}</dd>
                    @if ($order->expires_at)
                        <dt>Batas pembayaran</dt>
                        <dd>{{ $order->expires_at->format('d/m/Y H:i') }}</dd>
                    @endif
                </dl>

                @if ($supportUrl)
                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100">Butuh bantuan? WhatsApp</a>
                @endif
            </div>
        </div>
    </div>
@endsection
