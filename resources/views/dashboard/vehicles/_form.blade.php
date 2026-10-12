{{--
    Form bersama untuk tambah dan ubah kendaraan.
    Variabel: $action, $method (POST/PUT), $types, $vehicle (opsional, saat ubah).
--}}
@php
    $editing = isset($vehicle);
    $currentType = old('type', isset($vehicle) ? $vehicle->type->value : \App\VehicleType::Motorcycle->value);
    $isAvailable = (bool) old('is_available', $vehicle->is_available ?? true);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card-custom p-4">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="brand" class="form-label">Merek</label>
                <input type="text" id="brand" name="brand" value="{{ old('brand', $vehicle->brand ?? '') }}"
                    placeholder="Honda" class="form-control @error('brand') is-invalid @enderror" required autofocus>
                @error('brand')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="model" class="form-label">Model</label>
                <input type="text" id="model" name="model" value="{{ old('model', $vehicle->model ?? '') }}"
                    placeholder="Vario 125" class="form-control @error('model') is-invalid @enderror" required>
                @error('model')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="plate_number" class="form-label">Plat nomor</label>
                <input type="text" id="plate_number" name="plate_number" value="{{ old('plate_number', $vehicle->plate_number ?? '') }}"
                    placeholder="B 1234 XYZ" class="form-control text-uppercase @error('plate_number') is-invalid @enderror" required>
                @error('plate_number')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="type" class="form-label">Tipe</label>
                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($currentType === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-6 col-md-3">
                <label for="year" class="form-label">Tahun</label>
                <input type="number" id="year" name="year" value="{{ old('year', $vehicle->year ?? '') }}"
                    min="1990" max="{{ now()->year + 1 }}" class="form-control @error('year') is-invalid @enderror" required>
                @error('year')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-6 col-md-3">
                <label for="color" class="form-label">Warna</label>
                <input type="text" id="color" name="color" value="{{ old('color', $vehicle->color ?? '') }}"
                    class="form-control @error('color') is-invalid @enderror" required>
                @error('color')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-3">
                <label for="price_per_day" class="form-label">Tarif per hari (Rp)</label>
                <input type="number" id="price_per_day" name="price_per_day" value="{{ old('price_per_day', $vehicle->price_per_day ?? '') }}"
                    min="1" class="form-control @error('price_per_day') is-invalid @enderror" required>
                @error('price_per_day')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-3">
                <label for="price_per_hour" class="form-label">Tarif per jam (Rp)</label>
                <input type="number" id="price_per_hour" name="price_per_hour" value="{{ old('price_per_hour', $vehicle->price_per_hour ?? '') }}"
                    min="0" class="form-control @error('price_per_hour') is-invalid @enderror">
                <div class="form-text">Hanya keterangan. Total sewa dihitung memakai tarif harian.</div>
                @error('price_per_hour')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="photo" class="form-label">Foto kendaraan</label>
                <div class="d-flex flex-wrap align-items-start gap-3">
                    <img src="{{ $editing ? $vehicle->photo_url : asset('images/vehicle-placeholder.png') }}" alt="Pratinjau foto"
                        class="vehicle-photo-preview" data-photo-preview>
                    <div class="flex-grow-1">
                        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
                            class="form-control @error('photo') is-invalid @enderror" data-photo-input>
                        <div class="form-text">JPG, PNG, atau WebP, maksimal 1 MB. Foto yang lebih besar dikompres otomatis di browser.</div>
                        <div class="form-text" data-photo-status aria-live="polite"></div>
                        @error('photo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        @if ($editing && $vehicle->photo)
                            <input type="hidden" name="remove_photo" value="0">
                            <div class="form-check mt-2">
                                <input type="checkbox" id="remove_photo" name="remove_photo" value="1" class="form-check-input"
                                    @checked(old('remove_photo'))>
                                <label for="remove_photo" class="form-check-label">Hapus foto saat ini</label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12">
                <input type="hidden" name="is_available" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" id="is_available" name="is_available" value="1"
                        class="form-check-input" @checked($isAvailable)>
                    <label for="is_available" class="form-check-label">Tersedia untuk disewa</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('dashboard.vehicles.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
