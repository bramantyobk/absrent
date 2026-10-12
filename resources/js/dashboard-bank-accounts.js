// Data perusahaan: modal rekening bank (tambah dan ubah) dan buka ulang modal saat validasi gagal

const modalElement = document.getElementById('bankAccountModal');
const form = modalElement.querySelector('[data-bank-form]');
const title = form.querySelector('[data-bank-title]');

function setAction(url, method) {
    form.action = url;
    form.querySelector('[data-bank-method]').value = method;
    form.querySelector('[data-bank-action-field]').value = url;
    form.querySelector('[data-bank-method-field]').value = method;
}

modalElement.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;

    if (!trigger) {
        return;
    }

    if (trigger.hasAttribute('data-bank-edit')) {
        const data = trigger.dataset;

        title.textContent = 'Ubah rekening bank';
        setAction(data.bankUrl, 'PUT');
        form.elements.bank_name.value = data.bankName;
        form.elements.account_number.value = data.bankNumber;
        form.elements.account_holder.value = data.bankHolder;
        form.elements.sort_order.value = data.bankOrder;
        form.querySelector('#bank_is_active').checked = data.bankActive === '1';

        return;
    }

    title.textContent = 'Tambah rekening bank';
    setAction(trigger.dataset.bankUrl, 'POST');
    ['bank_name', 'account_number', 'account_holder', 'sort_order'].forEach((name) => {
        form.elements[name].value = '';
    });
    form.querySelector('#bank_is_active').checked = true;
});

const autoOpen = document.querySelector('[data-bank-accounts]')?.dataset.autoOpen;

if (autoOpen) {
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById(autoOpen)).show();
}
