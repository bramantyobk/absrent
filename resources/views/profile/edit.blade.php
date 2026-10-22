@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
    <h1 class="page-title mb-3">Profil saya</h1>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="booking-card mb-4">
                <h2 class="section-title fs-6">Foto profil</h2>

                <div class="d-flex align-items-center gap-3 mb-3">
                    @if ($user->photo_url)
                        <img src="{{ $user->photo_url }}" alt="Foto profil" class="avatar avatar-lg" data-photo-preview>
                    @else
                        <span class="avatar avatar-lg" aria-hidden="true">{{ $user->initials() }}</span>
                    @endif
                    <p class="small text-body-secondary mb-0">JPG, PNG, atau WebP, maksimal 1 MB. Foto yang lebih besar dikompres otomatis.</p>
                </div>

                <form method="POST" action="{{ route('profile.photo.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input
                        class="form-control @if ($photoErrors?->has('photo')) is-invalid @endif" aria-label="Pilih foto profil">
                    <div class="form-text" data-photo-status aria-live="polite"></div>
                    @if ($photoErrors?->has('photo'))
                        <div class="invalid-feedback d-block">{{ $photoErrors->first('photo') }}</div>
                    @endif

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">Simpan foto</button>
                    </div>
                </form>

                @if ($user->photo_path)
                    <form method="POST" action="{{ route('profile.photo.destroy') }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Hapus foto</button>
                    </form>
                @endif
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="booking-card" novalidate>
                @csrf
                @method('PUT')

                <h2 class="section-title fs-6">Data diri</h2>

                <div class="mb-3">
                    <label for="name" class="form-label">Nama</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name"
                        class="form-control @if ($profileErrors?->has('name')) is-invalid @endif" required>
                    @if ($profileErrors?->has('name'))
                        <div class="invalid-feedback">{{ $profileErrors->first('name') }}</div>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Nomor WhatsApp</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" inputmode="tel" autocomplete="tel"
                        placeholder="081234567890" class="form-control @if ($profileErrors?->has('phone')) is-invalid @endif" required>
                    @if ($profileErrors?->has('phone'))
                        <div class="invalid-feedback">{{ $profileErrors->first('phone') }}</div>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" value="{{ $user->email }}" class="form-control" disabled>
                    <div class="form-text">Email adalah identitas login dan tidak dapat diubah.</div>
                </div>

                <button type="submit" class="btn btn-primary">Simpan data diri</button>
            </form>
        </div>

        <div class="col-12 col-lg-6">
            <form method="POST" action="{{ route('profile.password.update') }}" class="booking-card" novalidate>
                @csrf
                @method('PUT')

                <h2 class="section-title fs-6">Ganti password</h2>

                <div class="mb-3">
                    <label for="current_password" class="form-label">Password lama</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                        class="form-control @if ($passwordErrors?->has('current_password')) is-invalid @endif" required>
                    @if ($passwordErrors?->has('current_password'))
                        <div class="invalid-feedback">{{ $passwordErrors->first('current_password') }}</div>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password baru</label>
                    <input type="password" id="password" name="password" autocomplete="new-password"
                        class="form-control @if ($passwordErrors?->has('password')) is-invalid @endif" required>
                    @if ($passwordErrors?->has('password'))
                        <div class="invalid-feedback">{{ $passwordErrors->first('password') }}</div>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Ulangi password baru</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Ganti password</button>
            </form>
        </div>
    </div>
@endsection
