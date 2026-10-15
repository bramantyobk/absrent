// Detail pesanan: dialog ganti kendaraan (filter unit menurut alasan, kartu saran biaya, bagian unit lama dan unit baru)
// dan dialog penyelesaian unit lama yang ditunda

const rupiah = (value) => `Rp${Math.abs(value).toLocaleString('id-ID')}`;
const signed = (value) => `${value >= 0 ? '+' : '-'}${rupiah(value)}`;

document.querySelectorAll('[data-vehicle-change]').forEach((modal) => {
    const days = Number(modal.dataset.days);
    const oldPrice = Number(modal.dataset.oldPrice);
    const oldType = modal.dataset.oldType;
    const orderTotal = Number(modal.dataset.orderTotal);
    const reason = modal.querySelector('[data-change-reason]');
    const vehicle = modal.querySelector('[data-change-vehicle]');
    const amount = modal.querySelector('[data-change-amount]');
    let amountTouched = amount.value !== '';

    const selectedVehicle = () => vehicle.selectedOptions[0];

    const filterVehicles = () => {
        const sameCategory = reason.selectedOptions[0]?.dataset.sameCategory === '1';

        vehicle.querySelectorAll('option[data-type]').forEach((option) => {
            const hidden = sameCategory && option.dataset.type !== oldType;

            option.hidden = hidden;
            option.disabled = hidden;
        });

        if (selectedVehicle()?.disabled) {
            vehicle.value = '';
        }
    };

    const renderSuggestion = () => {
        const option = selectedVehicle();
        const hasVehicle = Boolean(option?.dataset.price);
        const newPrice = hasVehicle ? Number(option.dataset.price) : null;
        const suggested = hasVehicle ? days * (newPrice - oldPrice) : null;

        modal.querySelector('[data-suggest-new]').textContent = hasVehicle ? `${rupiah(newPrice)}/hari` : '-';
        modal.querySelector('[data-suggest-amount]').textContent = hasVehicle ? signed(suggested) : '-';
        modal.querySelector('[data-suggest-label]').textContent = hasVehicle ? (suggested >= 0 ? 'tambah bayar' : 'refund') : '-';

        if (hasVehicle && !amountTouched) {
            amount.value = suggested;
        }

        const final = amount.value === '' ? null : Number(amount.value);

        modal.querySelector('[data-suggest-total]').textContent = final === null ? '-' : rupiah(orderTotal + final);
    };

    const renderPickup = () => {
        const option = selectedVehicle();
        const odometer = modal.querySelector('[data-pickup-odometer]');

        modal.querySelectorAll('[data-pickup-checklist]').forEach((section) => {
            const active = Boolean(option?.dataset.type) && section.dataset.pickupChecklist === option.dataset.type;

            section.classList.toggle('d-none', !active);
            section.querySelectorAll('input').forEach((input) => {
                input.disabled = !active;
            });
        });

        if (odometer && option?.dataset.odometer !== undefined) {
            odometer.min = option.dataset.odometer;
            odometer.value = odometer.value === '' || Number(odometer.value) < Number(option.dataset.odometer) ? option.dataset.odometer : odometer.value;
            modal.querySelector('[data-pickup-minimum]').textContent = `Minimal ${Number(option.dataset.odometer).toLocaleString('id-ID')} km.`;
        }
    };

    const renderOldUnit = () => {
        const status = modal.querySelector('[data-old-status]:checked')?.value;

        modal.querySelector('[data-old-section="returned"]')?.classList.toggle('d-none', status !== 'returned');
        modal.querySelector('[data-old-section="unreturned"]')?.classList.toggle('d-none', status === 'returned');
    };

    reason.addEventListener('change', () => {
        filterVehicles();
        renderSuggestion();
        renderPickup();
    });
    vehicle.addEventListener('change', () => {
        renderSuggestion();
        renderPickup();
    });
    amount.addEventListener('input', () => {
        amountTouched = true;
        renderSuggestion();
    });
    modal.querySelectorAll('[data-old-status]').forEach((radio) => radio.addEventListener('change', renderOldUnit));

    filterVehicles();
    renderSuggestion();
    renderPickup();
    renderOldUnit();
});

document.querySelectorAll('[data-old-unit]').forEach((modal) => {
    const render = () => {
        const action = modal.querySelector('[data-resolve-action]:checked')?.value;

        modal.querySelectorAll('[data-resolve-section]').forEach((section) => {
            section.classList.toggle('d-none', section.dataset.resolveSection !== action);
        });
    };

    modal.querySelectorAll('[data-resolve-action]').forEach((radio) => radio.addEventListener('change', render));
    render();
});
