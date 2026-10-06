<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ABSRENT') }}</title>
        @fonts
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <main class="container py-5">
            <h1 class="fw-bold text-primary">{{ config('app.name', 'ABSRENT') }}</h1>
            <p class="text-body-secondary">Aplikasi sewa kendaraan.</p>

            @auth
                <p>Masuk sebagai <strong>{{ auth()->user()->name }}</strong>.</p>
                <div class="d-flex gap-2">
                    @if (auth()->user()->isStaff())
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Keluar</button>
                    </form>
                </div>
            @else
                <div class="d-flex gap-2">
                    <a href="{{ route('login') }}" class="btn btn-primary">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-outline-primary">Daftar</a>
                </div>
            @endauth
        </main>
    </body>
</html>
