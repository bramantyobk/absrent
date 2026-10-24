@extends('layouts.guest')

@section('title', 'Atur Ulang Password')

@section('content')
    <h1 class="h4 fw-bold mb-1">Atur ulang password</h1>
    <p class="text-body-secondary mb-4">Tautan ini berlaku {{ config('auth.passwords.users.expire') }} menit dan hanya sekali pakai.</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ old('email', $email) }}">

        @error('email')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror

        <div class="mb-3">
            <label for="password" class="form-label">Password baru</label>
            <input type="password" id="password" name="password" autocomplete="new-password"
                class="form-control @error('password') is-invalid @enderror" required autofocus>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Ulangi password baru</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Simpan password</button>
    </form>
@endsection
