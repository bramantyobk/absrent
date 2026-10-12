// Kompres foto kendaraan di browser agar ukuran upload maksimal 1 MB (batas server: 1024 KB).
// Gambar diperkecil bertahap dan kualitas JPEG diturunkan sampai muat. Jika gagal, file asli dikirim
// apa adanya dan server yang menolak dengan pesan validasi.

const MAX_BYTES = 1000 * 1000;
const MAX_DIMENSION = 1600;
const QUALITY_STEPS = [0.85, 0.75, 0.65, 0.55, 0.45];
const MIN_DIMENSION = 400;

const input = document.querySelector('[data-photo-input]');
const preview = document.querySelector('[data-photo-preview]');
const status = document.querySelector('[data-photo-status]');

function setStatus(message) {
    status.textContent = message;
}

function formatSize(bytes) {
    return bytes >= 1000 * 1000 ? `${(bytes / 1000 / 1000).toFixed(2)} MB` : `${Math.round(bytes / 1000)} KB`;
}

function toBlob(canvas, quality) {
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
}

async function compress(file) {
    // imageOrientation menghormati rotasi EXIF dari kamera HP
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    let longest = Math.min(Math.max(bitmap.width, bitmap.height), MAX_DIMENSION);

    while (longest >= MIN_DIMENSION) {
        const scale = longest / Math.max(bitmap.width, bitmap.height);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);

        const context = canvas.getContext('2d');
        context.fillStyle = '#fff'; // PNG/WebP transparan menjadi putih, bukan hitam
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        for (const quality of QUALITY_STEPS) {
            const blob = await toBlob(canvas, quality);

            if (blob && blob.size <= MAX_BYTES) {
                bitmap.close();

                return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
            }
        }

        longest = Math.round(longest * 0.8);
    }

    bitmap.close();

    return null;
}

input.addEventListener('change', async () => {
    let file = input.files[0];

    if (!file) {
        return;
    }

    setStatus('');

    if (file.size > MAX_BYTES) {
        setStatus('Mengompres foto...');
        input.disabled = true;

        try {
            const compressed = await compress(file);

            if (!compressed) {
                setStatus('Foto tidak dapat dikompres sampai 1 MB. Pilih foto lain.');
                input.value = '';

                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(compressed);
            input.files = transfer.files;
            setStatus(`Foto dikompres dari ${formatSize(file.size)} menjadi ${formatSize(compressed.size)}.`);
            file = compressed;
        } catch {
            setStatus('Foto tidak dapat dikompres di browser. Pilih foto lain atau perkecil dulu.');
            input.value = '';

            return;
        } finally {
            input.disabled = false;
        }
    }

    if (preview) {
        preview.src = URL.createObjectURL(file);
    }
});
