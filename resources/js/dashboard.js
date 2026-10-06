const SIDEBAR_COLLAPSED_KEY = 'absrentSidebarCollapsed';

try {
    if (localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
} catch {
    // localStorage tidak tersedia, sidebar tetap terbuka
}

document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    const collapsed = document.body.classList.toggle('sidebar-collapsed');

    try {
        localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0');
    } catch {
        // abaikan
    }
});

if (document.querySelector('[data-chart]')) {
    import('./dashboard-charts');
}

// Modal konfirmasi hapus: isi URL dan nama dari tombol pemicu
document.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;

    if (!trigger?.dataset.deleteUrl) {
        return;
    }

    const form = event.target.querySelector('[data-confirm-delete-form]');

    form.action = trigger.dataset.deleteUrl;
    form.querySelector('[data-confirm-delete-name]').textContent = trigger.dataset.deleteName;
});

// Tampilkan switch "sedang bertugas" hanya untuk role operator
document.querySelectorAll('[data-role-select]').forEach((select) => {
    const field = document.querySelector('[data-duty-field]');

    select.addEventListener('change', () => {
        field?.classList.toggle('d-none', select.value !== 'operator');
    });
});

if (document.querySelector('[data-duty-toggle]')) {
    import('./dashboard-duty-toggle');
}

