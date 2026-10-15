<div class="modal fade" id="stolenModal" tabindex="-1" aria-labelledby="stolenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('dashboard.orders.status.update', $order) }}" class="modal-content" novalidate>
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="stolen">

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="stolenModalLabel">Tandai pesanan Dicuri?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-body-secondary">
                    Pesanan menjadi Dicuri (akhir, tanpa inspeksi pengembalian). Unit disembunyikan dari katalog sampai ditangani admin.
                    Ganti rugi diurus manual di luar aplikasi.
                </p>
                <label for="stolen_note" class="form-label">Catatan</label>
                <textarea id="stolen_note" name="note" rows="3" maxlength="1000" class="form-control @error('note') is-invalid @enderror" required>{{ old('note') }}</textarea>
                @error('note')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Tandai Dicuri</button>
            </div>
        </form>
    </div>
</div>
