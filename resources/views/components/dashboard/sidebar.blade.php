{{-- Desktop (>= lg): sidebar yang bisa diciutkan (lihat resources/js/dashboard.js) --}}
<aside class="sidebar d-none d-lg-block">
    <button id="sidebarToggle" class="sidebar-toggle-btn" type="button" aria-label="Perkecil sidebar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>

    <x-dashboard.nav-items :collapsible="true" />

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="sidebar-logout" title="Keluar">
            <span class="sidebar-icon" style="--icon: url('{{ asset('images/icons/logout.svg') }}')" aria-hidden="true"></span>
            <span class="sidebar-link-label">Keluar</span>
        </button>
    </form>
</aside>
