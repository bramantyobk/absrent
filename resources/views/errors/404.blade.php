@extends('layouts.app')

@section('title', 'Halaman tidak ditemukan')

@section('content')
    <div class="empty-state mx-auto" style="max-width: 36rem;">
        <i class="bi bi-compass display-4 text-primary" aria-hidden="true"></i>
        <h1 class="page-title mt-3 mb-2">Halaman tidak ditemukan</h1>
        <p class="mb-4">Alamat yang Anda buka tidak ada atau sudah dipindahkan. Periksa kembali alamatnya, atau kembali ke halaman utama.</p>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('home') }}" class="btn btn-primary"><i class="bi bi-house me-2" aria-hidden="true"></i>Ke beranda</a>
            <a href="{{ route('vehicles.index') }}" class="btn btn-outline-primary"><i class="bi bi-car-front me-2" aria-hidden="true"></i>Lihat kendaraan</a>
            @if (auth()->user()?->isStaff())
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Ke dashboard</a>
            @endif
        </div>
    </div>
@endsection
