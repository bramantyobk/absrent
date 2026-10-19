// Halaman pelanggan: hitung sewa langsung di detail kendaraan
if (document.querySelector('[data-rental-quote]')) {
    import('./customer-rental-quote');
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
