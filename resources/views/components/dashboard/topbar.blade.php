{{-- Desktop (>= lg): navbar atas dengan menu user --}}
<header class="top-navbar d-none d-lg-flex align-items-center px-5">
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <x-brand :href="route('dashboard')" :size="48" class="brand-logo-lg" />

        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ auth()->user()->name }}
                <span class="badge text-bg-primary ms-1">{{ auth()->user()->role->label() }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">Keluar</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
