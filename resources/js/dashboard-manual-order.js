// Pesanan manual: batas jumlah kendaraan, pilihan pelanggan yang ada atau baru, dan pencarian pelanggan lewat endpoint lookup

const form = document.querySelector('[data-manual-order]');
const maxVehicles = Number(form.dataset.maxVehicles);

const vehicleChoices = form.querySelectorAll('[data-vehicle-choice]');

const syncVehicleLimit = () => {
    const chosen = [...vehicleChoices].filter((choice) => choice.checked).length;

    vehicleChoices.forEach((choice) => {
        choice.disabled = !choice.checked && chosen >= maxVehicles;
    });
};

vehicleChoices.forEach((choice) => choice.addEventListener('change', syncVehicleLimit));
syncVehicleLimit();

const sections = form.querySelectorAll('[data-customer-section]');
const newFields = form.querySelectorAll('[data-new-field]');
const customerId = form.querySelector('[data-customer-id]');

const syncCustomerMode = () => {
    const mode = form.querySelector('[data-customer-mode]:checked')?.value ?? 'existing';

    sections.forEach((section) => section.classList.toggle('d-none', section.dataset.customerSection !== mode));
    newFields.forEach((field) => {
        field.disabled = mode !== 'new';
    });
    customerId.disabled = mode !== 'existing';
};

form.querySelectorAll('[data-customer-mode]').forEach((radio) => radio.addEventListener('change', syncCustomerMode));
syncCustomerMode();

const results = form.querySelector('[data-lookup-results]');
const errorBox = form.querySelector('[data-lookup-error]');
const selected = form.querySelector('[data-customer-selected]');

const selectCustomer = (customer) => {
    customerId.value = customer.id;
    form.querySelector('[data-customer-label]').value = `${customer.name} (${customer.phone})`;
    form.querySelector('[data-customer-selected-name]').textContent = `${customer.name} (${customer.phone})`;
    selected.classList.remove('d-none');
};

const renderResults = (customers) => {
    results.replaceChildren();

    if (customers.length === 0) {
        const empty = document.createElement('p');

        empty.className = 'small text-body-secondary mb-0';
        empty.textContent = 'Pelanggan tidak ditemukan. Pilih "Pelanggan baru" untuk membuat akun.';
        results.append(empty);

        return;
    }

    customers.forEach((customer) => {
        const button = document.createElement('button');

        button.type = 'button';
        button.className = 'btn btn-outline-secondary text-start';
        button.textContent = `${customer.name} · ${customer.phone} · ${customer.email}${customer.phone_flagged ? ' · nomor perlu ditinjau' : ''}`;
        button.addEventListener('click', () => selectCustomer(customer));
        results.append(button);
    });
};

form.querySelector('[data-lookup-button]').addEventListener('click', async () => {
    const params = new URLSearchParams();
    const phone = form.querySelector('[data-lookup-phone]').value.trim();
    const query = form.querySelector('[data-lookup-query]').value.trim();

    if (phone) {
        params.set('phone', phone);
    }

    if (query) {
        params.set('q', query);
    }

    errorBox.classList.add('d-none');

    if ([...params].length === 0) {
        errorBox.textContent = 'Isi nomor WhatsApp, email, atau nama.';
        errorBox.classList.remove('d-none');

        return;
    }

    const response = await fetch(`${form.dataset.lookupUrl}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

    if (!response.ok) {
        errorBox.textContent = 'Pencarian gagal. Periksa isian lalu coba lagi.';
        errorBox.classList.remove('d-none');

        return;
    }

    renderResults((await response.json()).data);
});
