@extends('layouts.app')

@section('title', 'Sewa Kendaraan')

@section('content')
    <section class="customer-hero mb-5">
        <div class="row align-items-center g-4">
            <div class="col-12 col-lg-7">
                <h1 class="customer-hero-title">Sewa motor dan mobil dengan mudah, kapan saja.</h1>
                <p class="customer-hero-text">
                    Pilih kendaraan, tentukan jadwal, dan pesan langsung dari HP. Pemesanan online dilayani 24 jam.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('vehicles.index') }}" class="btn btn-primary btn-lg">Lihat kendaraan</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('vehicles.index', ['type' => $category->value]) }}" class="btn btn-outline-primary btn-lg">{{ $category->label() }}</a>
                    @endforeach
                </div>
            </div>
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
                ['Pilih kendaraan', 'Telusuri motor dan mobil yang tersedia di katalog.'],
                ['Tentukan jadwal', 'Pilih waktu mulai dan selesai, tarif dihitung per hari.'],
                ['Buat pesanan', 'Tambah kendaraan lain bila perlu, lalu periksa total di halaman pesanan.'],
                ['Bayar dan konfirmasi', 'Transfer ke rekening perusahaan lalu konfirmasi lewat WhatsApp.'],
            ] as $index => [$title, $text])
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="step-card h-100">
                        <span class="step-number">{{ $index + 1 }}</span>
                        <h3 class="step-title">{{ $title }}</h3>
                        <p class="small text-body-secondary mb-0">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
