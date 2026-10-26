<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') - {{ config('app.name') }}</title>
        <x-favicon />
        @fonts
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body class="auth-body">
        <div class="auth-split">
            <section class="auth-panel">
                <div class="auth-panel-inner">
                    <x-brand :size="44" class="mb-4" />

                    @yield('content')
                </div>

                <p class="auth-back">
                    <a href="{{ route('home') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke beranda</a>
                </p>
            </section>

            {{-- Hanya desktop: slideshow dekoratif dengan tiga alasan memakai layanan --}}
            <aside class="auth-visual" aria-hidden="true">
                <div id="authSlides" class="carousel slide carousel-fade hero-slides" data-bs-ride="carousel" data-bs-interval="6000">
                    <div class="carousel-inner h-100">
                        <div class="carousel-item active hero-slide hero-slide-1"></div>
                        <div class="carousel-item hero-slide hero-slide-2"></div>
                    </div>
                </div>

                <div class="auth-visual-copy">
                    <h2>Sewa motor dan mobil dengan mudah, kapan saja.</h2>
                    <ul>
                        <li><i class="bi bi-clock-history" aria-hidden="true"></i>Pemesanan online dilayani 24 jam</li>
                        <li><i class="bi bi-whatsapp" aria-hidden="true"></i>Konfirmasi cepat lewat WhatsApp</li>
                        <li><i class="bi bi-shield-check" aria-hidden="true"></i>Unit terawat dan diperiksa setiap sewa</li>
                    </ul>
                </div>
            </aside>
        </div>
    </body>
</html>
