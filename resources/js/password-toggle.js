// Halaman auth: tambahkan tombol mata untuk menampilkan atau menyembunyikan isi kolom password
document.querySelectorAll('.auth-panel input[type="password"]').forEach((input) => {
    const wrapper = document.createElement('div');
    const button = document.createElement('button');
    const icon = document.createElement('i');

    wrapper.className = 'password-field';
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-label', 'Tampilkan password');
    icon.className = 'bi bi-eye';
    icon.setAttribute('aria-hidden', 'true');
    button.append(icon);

    input.replaceWith(wrapper);
    wrapper.append(input, button);

    // Pesan error Bootstrap (.invalid-feedback) mengikuti saudara .is-invalid, jadi harus dipindah ke luar wrapper
    const feedback = wrapper.nextElementSibling;

    if (feedback?.classList.contains('invalid-feedback') && input.classList.contains('is-invalid')) {
        feedback.classList.add('d-block');
    }

    button.addEventListener('click', () => {
        const visible = input.type === 'text';

        input.type = visible ? 'password' : 'text';
        icon.className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
        button.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
    });
});
