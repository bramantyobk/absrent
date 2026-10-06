@props(['collapsible' => false])

@php
    $items = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'home'],
        ['label' => 'Daftar Pesanan', 'route' => 'dashboard.orders.index', 'pattern' => 'dashboard.orders.*', 'icon' => 'wallet'],
        ['label' => 'Kendaraan', 'route' => 'dashboard.vehicles.index', 'pattern' => 'dashboard.vehicles.*', 'icon' => 'car'],
        ['label' => 'Pengguna', 'route' => 'dashboard.users.index', 'pattern' => 'dashboard.users.*', 'icon' => 'users'],
    ];
@endphp

<p class="sidebar-heading mb-4">Main Menu</p>
<nav class="sidebar-nav">
    @foreach ($items as $item)
        @php
            $exists = Route::has($item['route']);
            $active = $exists && request()->routeIs($item['pattern']);
        @endphp

        <{{ $exists ? 'a' : 'span' }}
            @if ($exists) href="{{ route($item['route']) }}" @else aria-disabled="true" @endif
            @class(['sidebar-link', 'active' => $active, 'disabled' => ! $exists])
            @if ($collapsible) title="{{ $item['label'] }}" @endif
            @if ($active) aria-current="page" @endif
        >
            <span class="sidebar-icon" style="--icon: url('{{ asset('images/icons/'.$item['icon'].'.svg') }}')" aria-hidden="true"></span>
            <span @class(['sidebar-link-label' => $collapsible])>{{ $item['label'] }}</span>
        </{{ $exists ? 'a' : 'span' }}>
    @endforeach
</nav>
