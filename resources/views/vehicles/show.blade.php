@extends('layouts.app')

@section('title', "{$vehicle->brand} {$vehicle->model}")

@php
    $inputFormat = 'Y-m-d\TH:i';
    $prefill = function (string $key) use ($inputFormat): string {
        $value = old($key, request($key));

        return $value ? rescue(fn () => \Illuminate\Support\Carbon::parse($value)->format($inputFormat), '', false) : '';
    };
    $isCustomer = auth()->check() && ! auth()->user()->isStaff();
@endphp

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <a href="{{ route('vehicles.index') }}" class="small fw-semibold">&larr; Semua kendaraan</a>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <img src="{{ $vehicle->photo_url }}" alt="{{ $vehicle->brand }} {{ $vehicle->model }}" class="vehicle-hero-photo mb-3">

            <h1 class="page-title mb-1">{{ $vehicle->brand }} {{ $vehicle->model }}</h1>
            <div class="d-flex flex-wrap gap-1 mb-3">
                <span class="vehicle-chip">{{ $vehicle->type->label() }}</span>
                @if ($vehicle->drive_type)
                    <span class="vehicle-chip">{{ $vehicle->drive_type->label() }}</span>
                @endif
            </div>

            @if ($vehicle->description)
                <p>{{ $vehicle->description }}</p>
            @endif

            <h2 class="section-title fs-6">Spesifikasi</h2>
            <dl class="row spec-list mb-4">
                <dt class="col-6 col-md-4">Tahun</dt>
                <dd class="col-6 col-md-8">{{ $vehicle->year }}</dd>
                <dt class="col-6 col-md-4">Warna</dt>
                <dd class="col-6 col-md-8">{{ $vehicle->color }}</dd>
                @if ($vehicle->energyCapacityLabel())
                    <dt class="col-6 col-md-4">Kapasitas energi</dt>
                    <dd class="col-6 col-md-8">{{ $vehicle->energyCapacityLabel() }}</dd>
                @endif
                @if ($vehicle->price_per_hour !== null)
                    <dt class="col-6 col-md-4">Tarif per jam (info)</dt>
                    <dd class="col-6 col-md-8">Rp{{ number_format($vehicle->price_per_hour, 0, ',', '.') }}</dd>
                @endif
                <dt class="col-6 col-md-4">Durasi sewa</dt>
                <dd class="col-6 col-md-8">{{ config('rental.min_rental_hours') }} jam sampai {{ $vehicle->effectiveMaxRentalDays() }} hari</dd>
            </dl>

            <h2 class="section-title fs-6">Perlengkapan</h2>
            @if ($vehicle->equipment->isEmpty())
                <p class="text-body-secondary">Belum ada informasi perlengkapan.</p>
            @else
                <ul class="list-unstyled equipment-list mb-0">
                    @foreach ($vehicle->equipment as $item)
                        <li><span>{{ $item->name }}</span> <span class="text-body-secondary">&times; {{ $item->quantity }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="col-12 col-lg-5">
            <div class="booking-card" data-rental-quote
                data-quote-url="{{ route('api.v1.vehicles.quote', $vehicle) }}">
                <p class="booking-price">
                    <strong>Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}</strong> <span>/ hari</span>
                </p>
                <p class="small text-body-secondary">Tarif dihitung per hari, dibulatkan ke atas per 24 jam.</p>

                @if ($isCustomer)
                    <form method="POST" action="{{ route('checkout.items.store', $vehicle) }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="start_at" class="form-label">Mulai</label>
                            <input type="datetime-local" id="start_at" name="start_at" value="{{ $prefill('start_at') }}"
                                min="{{ now()->format($inputFormat) }}" class="form-control @error('start_at') is-invalid @enderror" required data-quote-start>
                            @error('start_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="end_at" class="form-label">Selesai</label>
                            <input type="datetime-local" id="end_at" name="end_at" value="{{ $prefill('end_at') }}"
                                class="form-control @error('end_at') is-invalid @enderror" required data-quote-end>
                            @error('end_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="quote-result mb-3" data-quote-result aria-live="polite"></div>

                        <button type="submit" class="btn btn-primary w-100">Lanjut ke pesanan</button>
                    </form>
                @elseif (auth()->check())
                    <div class="alert alert-info mb-0">Akun staf tidak dapat membuat pesanan di halaman ini. Gunakan dashboard.</div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary w-100 mb-2">Masuk untuk memesan</a>
                    <a href="{{ route('register') }}" class="btn btn-outline-primary w-100">Daftar akun</a>
                @endif

                @if ($supportUrl)
                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100 mt-3">Tanya via WhatsApp</a>
                @endif
            </div>
        </div>
    </div>
@endsection
