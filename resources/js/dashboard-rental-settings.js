// Pengaturan sewa: tombol "Kembalikan ke bawaan" mengisi semua input dengan nilai bawaan (belum disimpan)

const form = document.querySelector('[data-rental-settings]');

form.querySelector('[data-rental-reset]').addEventListener('click', () => {
    form.querySelectorAll('input[data-default]').forEach((input) => {
        input.value = input.dataset.default;
    });
});
