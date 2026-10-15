// Detail kendaraan: memanggil /api/v1/vehicles/{id}/quote saat jadwal berubah dan menampilkan hari, subtotal, atau pesan kesalahan

const root = document.querySelector('[data-rental-quote]');
const start = root.querySelector('[data-quote-start]');
const end = root.querySelector('[data-quote-end]');
const result = root.querySelector('[data-quote-result]');
const money = new Intl.NumberFormat('id-ID');

let timer;
let controller;

function show(message, isError) {
    result.className = `quote-result mb-3 ${isError ? 'text-danger' : ''}`;
    result.innerHTML = message;
}

async function requestQuote() {
    if (!start?.value || !end?.value) {
        result.textContent = '';

        return;
    }

    controller?.abort();
    controller = new AbortController();

    const query = new URLSearchParams({ start_at: start.value, end_at: end.value });

    try {
        const response = await fetch(`${root.dataset.quoteUrl}?${query}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        const payload = await response.json();

        if (!response.ok) {
            const first = Object.values(payload.errors ?? {}).flat()[0];

            show(first ?? 'Jadwal tidak dapat dihitung.', true);

            return;
        }

        const { days, subtotal } = payload.data;

        show(`${days} hari &middot; <strong>Rp${money.format(subtotal)}</strong>`, false);
    } catch (error) {
        if (error.name !== 'AbortError') {
            show('Perhitungan tidak tersedia saat ini. Jadwal tetap diperiksa saat pesanan dibuat.', true);
        }
    }
}

[start, end].forEach((input) => {
    input?.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(requestQuote, 250);
    });
});

requestQuote();
