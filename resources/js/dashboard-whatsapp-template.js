// Data perusahaan: template pesan WhatsApp (sisip placeholder, pratinjau dengan data contoh, kembali ke bawaan)

const card = document.querySelector('[data-whatsapp-template]');
const input = card.querySelector('[data-template-input]');
const preview = card.querySelector('[data-template-preview]');
const sample = JSON.parse(card.dataset.sample);

function renderPreview() {
    preview.textContent = input.value.replace(/\{[a-z_]+\}/g, (placeholder) => sample[placeholder] ?? placeholder);
}

card.querySelectorAll('[data-template-placeholder]').forEach((button) => {
    button.addEventListener('click', () => {
        const { selectionStart, selectionEnd } = input;

        input.setRangeText(button.dataset.templatePlaceholder, selectionStart, selectionEnd, 'end');
        input.focus();
        renderPreview();
    });
});

card.querySelector('[data-template-reset]').addEventListener('click', () => {
    input.value = card.dataset.defaultTemplate;
    renderPreview();
});

input.addEventListener('input', renderPreview);
renderPreview();
