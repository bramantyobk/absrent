// Detail kendaraan: saat waktu mulai berubah, ambil opsi durasi dari /api/v1/vehicles/{id}/return-options dan isi select
// durasi (tiap opsi memuat tanggal dan jam pengembalian). Subtotal opsi terpilih ditampilkan.

const root = document.querySelector('[data-rental-quote]');
const start = root.querySelector('[data-quote-start]');
const select = root.querySelector('[data-quote-days]');

function init() {
    if (!start || !select) {
        return;
    }

    const result = root.querySelector('[data-quote-result]');
    const notice = root.querySelector('[data-quote-notice]');
    const money = new Intl.NumberFormat('id-ID');

    let timer;
    let controller;

    // Hanya textContent dan node buatan sendiri yang dipakai agar teks dari server tidak pernah diperlakukan sebagai HTML
    function showTotal() {
        const option = select.selectedOptions[0];

        if (!option?.dataset.subtotal) {
            result.textContent = '';

            return;
        }

        const total = document.createElement('strong');

        total.textContent = `Rp${money.format(option.dataset.subtotal)}`;
        result.replaceChildren('Total ', total);
    }

    function showOptions(options) {
        const previous = select.value || root.dataset.selectedDays;

        select.replaceChildren();

        options.forEach((option) => {
            const element = document.createElement('option');

            element.value = option.days;
            element.dataset.subtotal = option.subtotal;
            element.textContent = `${option.days} hari – ${option.end_label}`;
            select.append(element);
        });

        if (options.some((option) => String(option.days) === String(previous))) {
            select.value = previous;
        }

        select.disabled = options.length === 0;
        notice.textContent = options.length === 0 ? 'Tidak tersedia pada waktu mulai ini.' : '';
        showTotal();
    }

    function showProblem(message) {
        select.replaceChildren(new Option('Pilih waktu mulai yang valid', ''));
        select.disabled = true;
        result.textContent = '';
        notice.textContent = message;
    }

    async function loadOptions() {
        if (!start.value) {
            showProblem('');

            return;
        }

        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(`${root.dataset.optionsUrl}?${new URLSearchParams({ start_at: start.value })}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const payload = await response.json();

            if (!response.ok) {
                showProblem(Object.values(payload.errors ?? {}).flat()[0] ?? 'Jadwal tidak dapat dihitung.');

                return;
            }

            showOptions(payload.data);
        } catch (error) {
            if (error.name !== 'AbortError') {
                showProblem('Pilihan durasi tidak tersedia saat ini. Jadwal tetap diperiksa saat pesanan dibuat.');
            }
        }
    }

    start.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(loadOptions, 250);
    });
    select.addEventListener('change', showTotal);

    showTotal();
}

init();
