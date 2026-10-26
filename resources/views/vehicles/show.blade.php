@extends('layouts.app')

@section('title', "{$vehicle->brand} {$vehicle->model}")

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <a href="{{ route('vehicles.index') }}" class="small fw-semibold"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Semua kendaraan</a>
    </nav>

    <section class="order-detail vehicle-detail" aria-label="Detail kendaraan">
        <div class="order-detail-grid">
            <div class="order-detail-main">
                <div class="vehicle-gallery">
                    <img src="{{ $vehicle->photo_url }}" alt="{{ $vehicle->brand }} {{ $vehicle->model }}">
                </div>

                <div class="d-flex flex-wrap gap-1 mt-4 mb-2">
                    <span class="vehicle-chip"><i class="bi bi-{{ $vehicle->type->value === 'car' ? 'car-front' : 'scooter' }} me-1" aria-hidden="true"></i>{{ $vehicle->type->label() }}</span>
                    @if ($vehicle->drive_type)
                        <span class="vehicle-chip"><i class="bi bi-gear me-1" aria-hidden="true"></i>{{ $vehicle->drive_type->label() }}</span>
                    @endif
                </div>
                <h1 class="page-title mb-1">{{ $vehicle->brand }} {{ $vehicle->model }}</h1>

                {{-- Mobile: harga langsung terlihat karena kartu pemesanan berada di bawah konten --}}
                <p class="vehicle-price-inline d-lg-none">
                    <strong>Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}</strong> <span>/ hari</span>
                </p>

                @if ($vehicle->description)
                    <p class="text-body-secondary mt-3 mb-0">{{ $vehicle->description }}</p>
                @endif

                <h2 class="section-title fs-6 mt-4">Spesifikasi</h2>
                <div class="spec-tiles">
                    <div class="spec-tile">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <span>Tahun</span>
                        <strong>{{ $vehicle->year }}</strong>
                    </div>
                    <div class="spec-tile">
                        <i class="bi bi-palette" aria-hidden="true"></i>
                        <span>Warna</span>
                        <strong>{{ $vehicle->color }}</strong>
                    </div>
                    @if ($vehicle->energyCapacityLabel())
                        <div class="spec-tile">
                            <i class="bi bi-{{ $vehicle->drive_type?->value === 'electric' ? 'ev-front' : 'fuel-pump' }}" aria-hidden="true"></i>
                            <span>Kapasitas energi</span>
                            <strong>{{ $vehicle->energyCapacityLabel() }}</strong>
                        </div>
                    @endif
                </div>

                <h2 class="section-title fs-6 mt-4">Perlengkapan</h2>
                @if ($vehicle->equipment->isEmpty())
                    <p class="text-body-secondary mb-0">Belum ada informasi perlengkapan.</p>
                @else
                    <ul class="equip-grid">
                        @foreach ($vehicle->equipment as $item)
                            <li>
                                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                <span>{{ $item->name }}</span>
                                <span class="text-body-secondary ms-auto">&times; {{ $item->quantity }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <aside class="order-detail-side">
                <div class="vehicle-booking" data-rental-quote
                    data-options-url="{{ route('api.v1.vehicles.return-options', $vehicle) }}"
                    data-selected-days="{{ $days }}">
                    <p class="vehicle-price d-none d-lg-block">
                        <strong>Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}</strong> <span>/ hari</span>
                    </p>

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
                                <div class="booking-panel mb-3">
                                    <div class="mb-3">
                                        <x-datetime-field id="start_at" label="Mulai" label-class="booking-label" :value="$startAt" :timezone="$timezoneLabel"
                                            :min-date="now()->format('Y-m-d')" required data-quote-start />
                                    </div>
                                    <div class="mb-3">
                                        <label for="days" class="booking-label">Durasi dan waktu pengembalian</label>
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
                                    <div class="quote-result" data-quote-result aria-live="polite"></div>
                                </div>
                            @endif

                            <button type="submit" class="btn btn-primary w-100 summary-submit" data-quote-submit>{{ $isLocked ? 'Tambah ke pesanan' : 'Lanjut ke pesanan' }}<i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button>
                        </form>
                    @elseif (auth()->check())
                        <div class="alert alert-info mb-0">Akun staf tidak dapat membuat pesanan di halaman ini. Gunakan dashboard.</div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary w-100 mb-2">Masuk untuk memesan</a>
                        <a href="{{ route('register') }}" class="btn btn-outline-primary w-100">Daftar akun</a>
                    @endif

                    @if ($supportUrl)
                        <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100 mt-3"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Tanya via WhatsApp</a>
                    @endif
                </div>
            </aside>
        </div>
    </section>
@endsection
