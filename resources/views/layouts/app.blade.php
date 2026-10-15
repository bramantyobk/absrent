<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') - {{ config('app.name') }}</title>
        <x-favicon />
        @fonts
        @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/js/customer.js'])
    </head>
    <body class="customer-body">
        <x-customer.navbar />

        <main class="container py-4 py-lg-5">
            @include('dashboard.users._alerts')

            @yield('content')
        </main>

        <x-customer.footer />
    </body>
</html>
