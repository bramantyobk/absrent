<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') - {{ config('app.name') }}</title>
        @fonts
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <main class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-7 col-lg-5">
                    <a href="{{ route('home') }}" class="d-block text-center fs-2 fw-bold text-primary mb-4">
                        {{ config('app.name') }}
                    </a>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            @yield('content')
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>
