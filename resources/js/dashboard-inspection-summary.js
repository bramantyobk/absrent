// Dialog ubah status dan ganti kendaraan: langkah ringkasan sebelum menyimpan. Tombol simpan membuka ringkasan, Kembali kembali ke
// form (isian tetap), dan Konfirmasi baru mengirim form. Ringkasan dibangun dari isian yang bertanda data-summary-*.

const element = (tag, className, text) => {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
};

const isActive = (field) => !field.disabled && !field.closest('.d-none');

const valueOf = (field) => {
    if (field.tagName === 'SELECT') {
        return field.value === '' ? '' : field.selectedOptions[0].textContent.trim();
    }

    return field.value.trim();
};

const collectGroups = (form) => {
    const groups = new Map();

    form.querySelectorAll('[data-summary-label], [data-summary-equipment]').forEach((field) => {
        if (!isActive(field) || (field.type === 'radio' && !field.checked)) {
            return;
        }

        const title = field.dataset.summaryGroup ?? field.closest('[data-summary-group]')?.dataset.summaryGroup ?? '';
        const rows = groups.get(title) ?? [];

        if (field.hasAttribute('data-summary-equipment')) {
            const expected = Number(field.dataset.expected);
            const checked = Number(field.value);

            rows.push({ label: field.dataset.name, value: `${checked} dari ${expected}`, warn: checked < expected });
        } else {
            rows.push({ label: field.dataset.summaryLabel, value: field.dataset.summaryText ?? (valueOf(field) || '-'), warn: false });
        }

        groups.set(title, rows);
    });

    return groups;
};

const renderSummary = (form, pane) => {
    pane.replaceChildren();

    if (form.dataset.summaryTitle) {
        pane.append(element('p', 'fw-semibold', form.dataset.summaryTitle));
    }

    pane.append(element('p', 'small text-body-secondary', 'Periksa ringkasan berikut. Pilih Kembali untuk mengubah, atau Konfirmasi untuk menyimpan.'));

    collectGroups(form).forEach((rows, title) => {
        if (title) {
            pane.append(element('h3', 'fs-6 fw-semibold mt-3', title));
        }

        const list = element('dl', 'row small mb-0');

        rows.forEach((row) => {
            list.append(element('dt', 'col-6 col-md-5 fw-normal text-body-secondary', row.label));
            list.append(element('dd', `col-6 col-md-7${row.warn ? ' text-danger fw-semibold' : ''}`, row.value));
        });

        pane.append(list);
    });
};

document.querySelectorAll('form[data-confirm-summary]').forEach((form) => {
    const body = form.querySelector('.modal-body');
    const footer = form.querySelector('.modal-footer');
    const pane = element('div', 'modal-body d-none');
    const paneFooter = element('div', 'modal-footer d-none');
    const back = element('button', 'btn btn-outline-secondary', 'Kembali');
    const confirm = element('button', 'btn btn-primary', 'Konfirmasi dan simpan');
    let confirmed = false;

    back.type = 'button';
    confirm.type = 'button';
    paneFooter.append(back, confirm);
    body.after(pane);
    footer.after(paneFooter);

    const show = (summary) => {
        body.classList.toggle('d-none', summary);
        footer.classList.toggle('d-none', summary);
        pane.classList.toggle('d-none', !summary);
        paneFooter.classList.toggle('d-none', !summary);
    };

    form.addEventListener('submit', (event) => {
        if (confirmed) {
            confirmed = false;

            return;
        }

        event.preventDefault();
        renderSummary(form, pane);
        show(true);
    });

    back.addEventListener('click', () => show(false));
    confirm.addEventListener('click', () => {
        confirmed = true;
        form.requestSubmit();
    });
    form.closest('.modal')?.addEventListener('hidden.bs.modal', () => show(false));
});
