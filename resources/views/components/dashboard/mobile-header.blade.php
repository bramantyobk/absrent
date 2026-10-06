{{-- Mobile (< lg): header dengan tombol menu, membuka #mobileMenu --}}
<header class="mobile-header d-flex d-lg-none align-items-center justify-content-between">
    <x-brand :href="route('dashboard')" :size="32" />
    <button class="btn p-0 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-label="Buka menu">
        <img src="{{ asset('images/icons/menu.svg') }}" alt="" width="24" height="24">
    </button>
</header>
