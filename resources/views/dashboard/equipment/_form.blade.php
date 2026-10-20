{{--
    Form bersama untuk tambah dan ubah perlengkapan.
    Variabel: $action, $method (POST/PUT), $categories, $equipment (opsional, saat ubah).
--}}
@php
    $editing = isset($equipment);
    $currentCategory = old('category', $editing ? $equipment->category->value : \App\VehicleType::Motorcycle->value);
    $isActive = (bool) old('is_active', $equipment->is_active ?? true);
@endphp

<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card-custom p-4">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="name" class="form-label">Nama perlengkapan</label>
                <input type="text" id="name" name="name" value="{{ old('name', $equipment->name ?? '') }}"
                    placeholder="Helm SNI" maxlength="100" class="form-control @error('name') is-invalid @enderror" required autofocus>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="category" class="form-label">Kategori</label>
                <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected($currentCategory === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
                @error('category')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-6 col-md-3">
                <label for="quantity" class="form-label">Jumlah default</label>
                <input type="number" id="quantity" name="quantity" min="1" max="99" value="{{ old('quantity', $equipment->quantity ?? 1) }}"
                    class="form-control @error('quantity') is-invalid @enderror" required>
                @error('quantity')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-6 col-md-3">
                <label for="sort_order" class="form-label">Urutan</label>
                <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $equipment->sort_order ?? '') }}"
                    class="form-control @error('sort_order') is-invalid @enderror">
                <div class="form-text">Kosongkan untuk menaruh di akhir daftar.</div>
                @error('sort_order')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                        class="form-check-input" @checked($isActive)>
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
                <div class="form-text">Item nonaktif tidak muncul pada inspeksi baru, dan riwayat inspeksi tidak berubah. Perlengkapan yang tidak dipakai lagi dapat dihapus dari daftar.</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('dashboard.equipment.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
