// Switch status bertugas operator: kirim PATCH JSON ke /dashboard/users/{user}/duty-status

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const alertBox = document.getElementById('dutyAlert');

function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('d-none');
}

function errorMessage(payload) {
    return payload?.errors?.is_on_duty?.[0] ?? payload?.message ?? 'Status bertugas gagal diperbarui.';
}

document.querySelectorAll('[data-duty-toggle]').forEach((toggle) => {
    const label = toggle.closest('.form-check').querySelector('[data-duty-label]');

    toggle.addEventListener('change', async () => {
        const wanted = toggle.checked;

        alertBox.classList.add('d-none');
        toggle.disabled = true;

        try {
            const response = await fetch(toggle.dataset.url, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ is_on_duty: wanted }),
            });
            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(errorMessage(payload));
            }

            toggle.checked = payload.is_on_duty;
            label.textContent = payload.is_on_duty ? 'Bertugas' : 'Libur';
        } catch (error) {
            toggle.checked = !wanted;
            showError(error.message);
        } finally {
            toggle.disabled = false;
        }
    });
});
