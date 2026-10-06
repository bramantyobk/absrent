<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') - {{ config('app.name') }}</title>
        <x-favicon />
        @fonts
        @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/js/dashboard.js'])
    </head>
    <body>
        <x-dashboard.topbar />
        <x-dashboard.sidebar />
        <x-dashboard.mobile-header />
        <x-dashboard.mobile-menu />

        <main class="dashboard-main">
            <h1 class="page-title mb-4">@yield('title')</h1>
            @yield('content')
        </main>
    </body>
</html>
