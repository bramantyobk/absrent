@extends('layouts.app')

@section('title', 'Sewa Kendaraan')

@section('hero')
    <section class="customer-hero">
        <div id="heroSlides" class="carousel slide carousel-fade hero-slides" data-bs-ride="carousel" data-bs-interval="6000" aria-hidden="true">
            <div class="carousel-inner h-100">
                <div class="carousel-item active hero-slide hero-slide-1"></div>
                <div class="carousel-item hero-slide hero-slide-2"></div>
            </div>
        </div>
        <div class="container hero-content">
            <div class="row align-items-center g-4">
                <div class="col-12 col-lg-7">
                    <h1 class="customer-hero-title">Sewa motor dan mobil dengan mudah, kapan saja.</h1>
                    <p class="customer-hero-text">
                        Pilih kendaraan, tentukan jadwal, dan pesan langsung dari HP. Pemesanan online dilayani 24 jam.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('vehicles.index') }}" class="btn btn-primary btn-lg"><i class="bi bi-search me-2" aria-hidden="true"></i>Lihat kendaraan</a>
                        @foreach ($categories as $category)
                            <a href="{{ route('vehicles.index', ['type' => $category->value]) }}" class="btn btn-outline-light btn-lg">{{ $category->label() }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('vehicles.index') }}" class="hero-search mt-4">
                <div class="row g-2 align-items-start">
                    <div class="col-12 col-md-6">
                        <x-datetime-field id="hero_start_at" label="Mulai sewa" :value="$defaultStart" :timezone="config('rental.timezone_label')"
                            :min-date="now()->format('Y-m-d')" show-return required />
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="hero_days" class="form-label">Durasi</label>
                        <select id="hero_days" name="days" class="form-select" required>
                            @foreach ($dayChoices as $dayChoice)
                                <option value="{{ $dayChoice }}">{{ $dayChoice }} hari</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <span class="form-label d-none d-md-block invisible" aria-hidden="true">&nbsp;</span>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2" aria-hidden="true"></i>Cari yang kosong</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('content')
    <section class="mb-5">
        <div class="row g-3">
            @foreach ([
                ['clock-history', 'Pesan 24 jam', 'Pemesanan online dilayani kapan saja.'],
                ['whatsapp', 'Konfirmasi via WhatsApp', 'Operator membalas langsung setelah Anda bayar.'],
                ['shield-check', 'Unit terawat', 'Setiap unit diperiksa saat diambil dan dikembalikan.'],
            ] as [$icon, $title, $text])
                <div class="col-12 col-md-4">
                    <div class="perk-item">
                        <i class="bi bi-{{ $icon }} perk-icon" aria-hidden="true"></i>
                        <div>
                            <h3 class="perk-title">{{ $title }}</h3>
                            <p class="small text-body-secondary mb-0">{{ $text }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-5">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2 class="section-title mb-0">Kendaraan tersedia</h2>
            <a href="{{ route('vehicles.index') }}" class="small fw-semibold">Lihat semua</a>
        </div>

        <div class="row g-3 g-lg-4">
            @forelse ($vehicles as $vehicle)
                <div class="col-12 col-sm-6 col-lg-4">
                    <x-customer.vehicle-card :vehicle="$vehicle" />
                </div>
            @empty
                <div class="col-12">
                    <p class="text-body-secondary">Belum ada kendaraan yang tersedia.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section>
        <h2 class="section-title">Cara memesan</h2>
        <div class="row g-3">
            @foreach ([
                ['search', 'Pilih kendaraan', 'Telusuri motor dan mobil yang tersedia di katalog.'],
                ['calendar-check', 'Tentukan jadwal', 'Pilih waktu mulai dan selesai, tarif dihitung per hari.'],
                ['bag-check', 'Buat pesanan', 'Tambah kendaraan lain bila perlu, lalu periksa total di halaman pesanan.'],
                ['credit-card', 'Bayar dan konfirmasi', 'Transfer ke rekening perusahaan lalu konfirmasi lewat WhatsApp.'],
            ] as [$icon, $title, $text])
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="step-card h-100">
                        <span class="step-number"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                        <h3 class="step-title">{{ $title }}</h3>
                        <p class="small text-body-secondary mb-0">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="cta-banner mt-5">
        <h2 class="cta-banner-title">Butuh bantuan memilih kendaraan?</h2>
        <p class="mb-3">Tanyakan langsung ke tim kami, kami bantu sesuaikan dengan kebutuhan perjalanan Anda.</p>
        @if ($supportUrl)
            <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="btn btn-light btn-lg"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Chat WhatsApp</a>
        @else
            <a href="{{ route('vehicles.index') }}" class="btn btn-light btn-lg">Lihat kendaraan</a>
        @endif
    </section>
@endsection
