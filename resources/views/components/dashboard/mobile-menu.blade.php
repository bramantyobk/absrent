<div class="offcanvas offcanvas-start mobile-offcanvas" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <x-brand :href="route('dashboard')" :size="32" />
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-body-secondary">{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</p>
        <x-dashboard.nav-items />

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="btn btn-outline-secondary w-100">Keluar</button>
        </form>
    </div>
</div>
