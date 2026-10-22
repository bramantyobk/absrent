@php
    $links = [
        ['label' => 'Beranda', 'route' => 'home', 'pattern' => 'home'],
        ['label' => 'Kendaraan', 'route' => 'vehicles.index', 'pattern' => 'vehicles.*'],
    ];
@endphp

<nav class="navbar navbar-expand-lg customer-navbar sticky-top">
    <div class="container">
        <x-brand :size="36" />

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#customerMenu"
            aria-controls="customerMenu" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="customerMenu" aria-labelledby="customerMenuLabel">
            <div class="offcanvas-header">
                <span class="offcanvas-title fw-semibold" id="customerMenuLabel">Menu</span>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
            </div>
            <div class="offcanvas-body align-items-lg-center">
                <ul class="navbar-nav me-auto gap-lg-2">
                    @foreach ($links as $link)
                        <li class="nav-item">
                            <a href="{{ route($link['route']) }}" @class(['nav-link', 'active' => request()->routeIs($link['pattern'])])>{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                    @if ($isCustomer)
                        <li class="nav-item">
                            <a href="{{ route('orders.index') }}" @class(['nav-link', 'active' => request()->routeIs('orders.*')])>Pesanan Saya</a>
                        </li>
                    @endif
                </ul>

                <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 mt-3 mt-lg-0 py-">
                    @auth
                        @if ($isCustomer)
                            <a href="{{ route('checkout.show') }}" class="btn btn-outline-primary position-relative py-2">
                                Keranjang
                                @if ($cartCount > 0)
                                    <span class="badge text-bg-primary ms-1">{{ $cartCount }}</span>
                                @endif
                            </a>
                        @else
                            <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
                        @endif
                        @if ($isCustomer)
                            <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary d-flex d-lg-none align-items-center gap-2 justify-content-center">
                                <x-customer.avatar :user="auth()->user()" />
                                <span class="text-truncate">{{ auth()->user()->name }}</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="d-lg-none">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100">Keluar</button>
                            </form>
                            <div class="dropdown d-none d-lg-block">
                                <button class="btn btn-outline-secondary d-flex align-items-center gap-2 w-100 justify-content-center" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <x-customer.avatar :user="auth()->user()" />
                                    <span class="text-truncate" style="max-width: 10rem;">{{ auth()->user()->name }}</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profil saya</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">Keluar</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        @else
                            <span class="small text-body-secondary px-lg-2">{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100">Keluar</button>
                            </form>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-primary">Masuk</a>
                        <a href="{{ route('register') }}" class="btn btn-primary">Daftar</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</nav>
