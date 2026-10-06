{{--
    Form bersama untuk tambah dan ubah pengguna.
    Variabel: $action, $method (POST/PUT), $roles, $user (opsional, saat ubah).
--}}
@php
    $editing = isset($user);
    $isSelf = $editing && $user->is(auth()->user());
    $currentRole = old('role', $user->role->value ?? \App\UserRole::User->value);
    $isActive = (bool) old('is_active', $user->is_active ?? true);
    $isOnDuty = (bool) old('is_on_duty', $user->is_on_duty ?? false);
@endphp

<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card-custom p-4">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="name" class="form-label">Nama lengkap</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name ?? '') }}"
                    class="form-control @error('name') is-invalid @enderror" required autofocus>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email ?? '') }}"
                    class="form-control @error('email') is-invalid @enderror" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="phone" class="form-label">Nomor WhatsApp</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone ?? '') }}"
                    placeholder="081234567890" class="form-control @error('phone') is-invalid @enderror" required>
                @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="role" class="form-label">Role</label>
                <select id="role" name="role" class="form-select @error('role') is-invalid @enderror"
                    data-role-select @disabled($isSelf) required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected($currentRole === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                @if ($isSelf)
                    <input type="hidden" name="role" value="{{ $currentRole }}">
                    <div class="form-text">Anda tidak dapat mengubah role akun sendiri.</div>
                @endif
                @error('role')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    autocomplete="new-password" @required(! $editing)>
                @if ($editing)
                    <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
                @endif
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-md-6">
                <label for="password_confirmation" class="form-label">Konfirmasi password</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                    class="form-control" autocomplete="new-password" @required(! $editing)>
            </div>

            <div class="col-12 col-md-6">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                        class="form-check-input @error('is_active') is-invalid @enderror"
                        @checked($isActive) @disabled($isSelf)>
                    <label for="is_active" class="form-check-label">Akun aktif</label>
                </div>
                @if ($isSelf)
                    <input type="hidden" name="is_active" value="1">
                    <div class="form-text">Anda tidak dapat menonaktifkan akun sendiri.</div>
                @endif
                @error('is_active')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div data-duty-field @class(['col-12 col-md-6', 'd-none' => $currentRole !== \App\UserRole::Operator->value])>
                <input type="hidden" name="is_on_duty" value="0">
                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" id="is_on_duty" name="is_on_duty" value="1"
                        class="form-check-input" @checked($isOnDuty)>
                    <label for="is_on_duty" class="form-check-label">Sedang bertugas</label>
                </div>
                <div class="form-text">Operator yang bertugas menerima konfirmasi pembayaran lewat WhatsApp (rotator).</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('dashboard.users.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
