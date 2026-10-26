// <x-datetime-field>: tanggal dan jam (24 jam, per 30 menit) digabung ke satu input tersembunyi "YYYY-MM-DDTHH:mm",
// sehingga server dan API tetap menerima satu nilai start_at.

const dayLabel = new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false });

function format(date, tz) {
    return `${dayLabel.format(date).replace(/\./g, ':').replace(',', '')} ${tz}`.trim();
}

function init(field) {
    const date = field.querySelector('[data-dt-date]');
    const time = field.querySelector('[data-dt-time]');
    const hidden = field.querySelector('[data-dt-value]');
    const summary = field.querySelector('[data-dt-summary]');
    const days = field.closest('form')?.querySelector('select[name="days"]');
    const tz = field.dataset.tz ?? '';

    // Jam di luar kelipatan 30 menit (data lama) tetap bisa dipilih
    if (hidden.value.length >= 16 && ![...time.options].some((option) => option.value === hidden.value.slice(11, 16))) {
        time.add(new Option(hidden.value.slice(11, 16), hidden.value.slice(11, 16), true, true));
    }

    function sync(notify) {
        const previous = hidden.value;

        hidden.value = date.value && time.value ? `${date.value}T${time.value}` : '';

        if (!hidden.value) {
            summary.textContent = '';
        } else {
            const start = new Date(`${hidden.value}:00`);
            let text = `Ambil ${format(start, tz)}`;

            if ('showReturn' in field.dataset && days?.value) {
                text += ` → Kembali ${format(new Date(start.getTime() + days.value * 86400000), tz)}`;
            }

            summary.textContent = text;
        }

        if (notify && hidden.value !== previous) {
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    date.addEventListener('input', () => sync(true));
    time.addEventListener('change', () => sync(true));
    days?.addEventListener('change', () => sync(false));
    sync(false);
}

document.querySelectorAll('[data-datetime-field]').forEach(init);
