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


// Foto kendaraan: kompres di browser (maks 1 MB) lalu tampilkan pratinjau
if (document.querySelector('[data-photo-input]')) {
    import('./dashboard-photo-upload');
}

// Form kendaraan: satuan kapasitas energi mengikuti tipe (liter atau kWh)
document.querySelectorAll('[data-drive-type-select]').forEach((select) => {
    const unit = document.querySelector('[data-energy-unit]');

    const syncUnit = () => {
        unit.textContent = select.selectedOptions[0]?.dataset.unit ?? 'liter';
    };

    select.addEventListener('change', syncUnit);
    syncUnit();
});

// Detail kendaraan: modal jadwal servis dan jendela kosong
if (document.querySelector('[data-vehicle-detail]')) {
    import('./dashboard-vehicle-detail');
}

// Data perusahaan: jam buka dan tutup hanya aktif untuk hari yang buka
document.querySelectorAll('[data-hours-row]').forEach((row) => {
    const toggle = row.querySelector('[data-hours-toggle]');
    const times = row.querySelectorAll('[data-hours-time]');

    toggle.addEventListener('change', () => {
        times.forEach((input) => {
            input.disabled = !toggle.checked;
        });
    });
});
