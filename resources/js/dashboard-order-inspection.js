// Detail pesanan: tombol cepat level energi pada dialog inspeksi dan buka ulang dialog saat validasi gagal

document.querySelectorAll('[data-inspection-unit]').forEach((unit) => {
    const input = unit.querySelector('[data-energy-input]');

    unit.querySelectorAll('[data-energy-quick]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.energyQuick;
        });
    });
});

const autoOpen = document.querySelector('[data-order-inspection]')?.dataset.autoOpen;

if (autoOpen) {
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById(autoOpen)).show();
}
