// Halaman pelanggan: hitung sewa langsung di detail kendaraan
if (document.querySelector('[data-rental-quote]')) {
    import('./customer-rental-quote');
}

// Profil: kompres foto di browser agar maksimal 1 MB
if (document.querySelector('[data-photo-input]')) {
    import('./dashboard-photo-upload');
}

// Tombol salin (nomor rekening, jumlah transfer): menyalin nilai data-copy lalu memberi umpan balik singkat
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');

    if (!button) {
        return;
    }

    const label = button.querySelector('span');
    const original = label.textContent;

    try {
        await navigator.clipboard.writeText(button.dataset.copy);
        label.textContent = 'Tersalin';
    } catch {
        // Cadangan untuk browser yang menolak Clipboard API (mis. halaman tanpa HTTPS)
        const field = Object.assign(document.createElement('textarea'), { value: button.dataset.copy });

        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.append(field);
        field.select();
        label.textContent = document.execCommand('copy') ? 'Tersalin' : 'Gagal menyalin';
        field.remove();
    }

    window.setTimeout(() => {
        label.textContent = original;
    }, 1500);
});

// Pembayaran: WhatsApp terbuka di tab baru, tab ini pindah ke detail pesanan karena statusnya sudah berubah
const confirmForm = document.querySelector('[data-payment-confirm]');

if (confirmForm) {
    confirmForm.addEventListener('submit', () => {
        window.setTimeout(() => {
            window.location.href = confirmForm.dataset.afterUrl;
        }, 600);
    });
}
