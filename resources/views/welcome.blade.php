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
            <p class="text-body-secondary">Aplikasi sewa kendaraan. Konfigurasi Bootstrap 5.3 aktif.</p>
            <a href="#" class="btn btn-primary">Mulai</a>
        </main>
    </body>
</html>
