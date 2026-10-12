// Detail kendaraan: modal jadwal servis (tambah, ubah, dari jendela kosong) dan buka ulang modal saat validasi gagal

const modalElement = document.getElementById('scheduleModal');
const form = modalElement.querySelector('[data-schedule-form]');
const title = form.querySelector('[data-schedule-title]');
const runningHint = form.querySelector('[data-schedule-running-hint]');
const field = (name) => form.elements[name];

function setAction(url, method) {
    form.action = url;
    form.querySelector('[data-schedule-method]').value = method;
    form.querySelector('[data-schedule-action-field]').value = url;
    form.querySelector('[data-schedule-method-field]').value = method;
}

function setRunning(isRunning) {
    field('start_at').readOnly = isRunning;
    runningHint.classList.toggle('d-none', !isRunning);
}

modalElement.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;

    if (!trigger) {
        return;
    }

    if (trigger.hasAttribute('data-schedule-edit')) {
        const data = trigger.dataset;

        title.textContent = 'Ubah jadwal servis';
        setAction(data.scheduleUrl, 'PUT');
        field('type').value = data.scheduleType;
        field('start_at').value = data.scheduleStart;
        field('end_at').value = data.scheduleEnd;
        field('note').value = data.scheduleNote;
        field('odometer_km').value = data.scheduleOdometer;
        setRunning(data.scheduleRunning === '1');

        return;
    }

    title.textContent = 'Jadwalkan servis';
    setAction(trigger.dataset.scheduleUrl, 'POST');
    ['start_at', 'end_at', 'note', 'odometer_km'].forEach((name) => {
        field(name).value = '';
    });
    field('type').selectedIndex = 0;
    setRunning(false);

    if (trigger.dataset.windowStart) {
        field('start_at').value = trigger.dataset.windowStart;
        field('end_at').value = trigger.dataset.windowEnd;
    }
});

const autoOpen = document.querySelector('[data-auto-open]')?.dataset.autoOpen;

if (autoOpen) {
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById(autoOpen)).show();
}
