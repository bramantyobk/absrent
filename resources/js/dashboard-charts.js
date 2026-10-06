import Chart from 'chart.js/auto';

const PRIMARY = '#3563e9';
const DOUGHNUT_PALETTE = ['#0D3559', '#175D9C', '#2185DE', '#63A9E8', '#A6CEF2'];

async function fetchJson(url, params = {}) {
    const query = new URLSearchParams(Object.entries(params).filter(([, value]) => value));
    const response = await fetch(`${url}?${query}`, { headers: { Accept: 'application/json' } });

    if (!response.ok) {
        throw new Error(`Gagal memuat grafik (${response.status})`);
    }

    return response.json();
}

function renderLegend(list, payload, colors) {
    list.replaceChildren(
        ...payload.labels.map((label, index) => {
            const item = document.createElement('li');
            const name = document.createElement('span');
            const dot = document.createElement('span');
            const value = document.createElement('span');

            name.className = 'legend-name';
            dot.className = 'legend-dot';
            dot.style.backgroundColor = colors[index];
            name.append(dot, label);

            value.textContent = payload.data[index];
            item.append(name, value);

            return item;
        }),
    );
}

async function initDoughnut(canvas) {
    const card = canvas.closest('[data-chart-card]');
    const payload = await fetchJson(canvas.dataset.url);
    const colors = payload.colors ?? DOUGHNUT_PALETTE;

    card.querySelector('[data-chart-total]').textContent = payload.total;
    card.querySelector('[data-chart-center-label]').textContent = payload.center_label;
    renderLegend(card.querySelector('[data-chart-legend]'), payload, colors);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: payload.labels,
            datasets: [{ data: payload.data, backgroundColor: colors, borderWidth: 0 }],
        },
        options: {
            cutout: '78%',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
        },
    });
}

function shortRupiah(value) {
    if (value >= 1_000_000) {
        return `${value / 1_000_000} jt`;
    }

    return value >= 1_000 ? `${value / 1_000} rb` : value;
}

async function initRevenue(canvas) {
    const type = document.getElementById('revenueType');
    const start = document.getElementById('revenueStart');
    const end = document.getElementById('revenueEnd');
    const total = document.getElementById('revenueTotal');
    const error = document.getElementById('revenueError');

    const chart = new Chart(canvas, {
        type: 'bar',
        data: { labels: [], datasets: [{ data: [], backgroundColor: PRIMARY, borderRadius: 4 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: { label: (context) => `Rp${context.parsed.y.toLocaleString('id-ID')}` },
                },
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: shortRupiah } },
                x: { grid: { display: false } },
            },
        },
    });

    async function load() {
        error.classList.add('d-none');

        try {
            const payload = await fetchJson(canvas.dataset.url, {
                type: type.value,
                start_date: start.value,
                end_date: end.value,
            });

            chart.data.labels = payload.labels;
            chart.data.datasets[0].data = payload.data;
            chart.update();
            total.textContent = payload.total_formatted;
        } catch (exception) {
            error.classList.remove('d-none');
            console.error(exception);
        }
    }

    [type, start, end].forEach((input) => input.addEventListener('change', load));
    load();
}

document.querySelectorAll('[data-chart="doughnut"]').forEach((canvas) => initDoughnut(canvas));

const revenueCanvas = document.querySelector('[data-chart="revenue"]');

if (revenueCanvas) {
    initRevenue(revenueCanvas);
}
