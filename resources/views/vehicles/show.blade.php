@extends('layouts.app')

@section('title', "{$vehicle->brand} {$vehicle->model}")

@php
    $inputFormat = 'Y-m-d\TH:i';
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
                data-options-url="{{ route('api.v1.vehicles.return-options', $vehicle) }}"
                data-selected-days="{{ $days }}">
                <p class="booking-price">
                    <strong>Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}</strong> <span>/ hari</span>
                </p>
                <p class="small text-body-secondary">Tarif dihitung per hari. Semua waktu dalam {{ $timezoneLabel }}.</p>

                @if ($isCustomer && $inCart)
                    <div class="alert alert-info">Kendaraan ini sudah ada di pesanan Anda.</div>
                    <a href="{{ route('checkout.show') }}" class="btn btn-primary w-100">Lihat pesanan</a>
                @elseif ($isCustomer && $isLocked && ! $canAddMore)
                    <div class="alert alert-info">Satu pesanan maksimal {{ $maxVehicles }} kendaraan.</div>
                    <a href="{{ route('checkout.show') }}" class="btn btn-primary w-100">Lihat pesanan</a>
                @elseif ($isCustomer)
                    <form method="POST" action="{{ route('checkout.items.store', $vehicle) }}" novalidate>
                        @csrf

                        @if ($isLocked)
                            <p class="small text-body-secondary mb-2">Jadwal mengikuti pesanan Anda:</p>
                            <p class="fw-semibold mb-3">{{ $startLabel }} {{ $timezoneLabel }}, {{ $days }} hari</p>
                            <input type="hidden" name="start_at" value="{{ $startAt }}">
                            <input type="hidden" name="days" value="{{ $days }}">
                            @error('start_at')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror
                        @else
                            <div class="mb-3">
                                <label for="start_at" class="form-label">Mulai ({{ $timezoneLabel }})</label>
                                <input type="datetime-local" id="start_at" name="start_at" value="{{ $startAt }}"
                                    min="{{ now()->format('Y-m-d\TH:i') }}" class="form-control @error('start_at') is-invalid @enderror" required data-quote-start>
                                @error('start_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="days" class="form-label">Durasi dan waktu pengembalian</label>
                                <select id="days" name="days" class="form-select @error('days') is-invalid @enderror" required data-quote-days @disabled($options->isEmpty())>
                                    @if ($options->isEmpty())
                                        <option value="">Pilih waktu mulai terlebih dahulu</option>
                                    @endif
                                    @foreach ($options as $option)
                                        <option value="{{ $option['days'] }}" data-subtotal="{{ $option['subtotal'] }}" @selected($days === $option['days'])>
                                            {{ $option['days'] }} hari &ndash; {{ $option['end_label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <p class="small text-danger mb-2" data-quote-notice>{{ $optionsNotice }}</p>
                            <div class="quote-result mb-3" data-quote-result aria-live="polite"></div>
                        @endif

                        <button type="submit" class="btn btn-primary w-100" data-quote-submit>{{ $isLocked ? 'Tambah ke pesanan' : 'Lanjut ke pesanan' }}</button>
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
