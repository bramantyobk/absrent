// Halaman pelanggan: hitung sewa langsung di detail kendaraan
if (document.querySelector('[data-rental-quote]')) {
    import('./customer-rental-quote');
}

// Profil: kompres foto di browser agar maksimal 1 MB
if (document.querySelector('[data-photo-input]')) {
    import('./dashboard-photo-upload');
}

// Pembayaran: WhatsApp terbuka di tab baru, tab ini pindah ke detail pesanan karena statusnya sudah berubah
const confirmForm = document.querySelector('[data-payment-confirm]');

if (confirmForm) {
    confirmForm.addEventListener('submit', () => {
        window.setTimeout(() => {
            window.location.href = confirmForm.dataset.afterUrl;
        }, 600);
    });
}
