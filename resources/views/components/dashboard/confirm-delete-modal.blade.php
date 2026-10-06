{{--
    Modal konfirmasi hapus yang dipakai bersama. Tombol pemicu cukup membawa:
    data-bs-toggle="modal" data-bs-target="#{id}" data-delete-url="..." data-delete-name="..."
    (lihat resources/js/dashboard.js)
--}}
@props(['id' => 'confirmDeleteModal', 'title' => 'Hapus data?'])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" data-confirm-delete-form>
            @csrf
            @method('DELETE')

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $id }}Label">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                Anda yakin ingin menghapus <strong data-confirm-delete-name></strong>? Tindakan ini tidak dapat dibatalkan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus</button>
            </div>
        </form>
    </div>
</div>
