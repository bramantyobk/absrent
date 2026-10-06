@extends('layouts.guest')

@section('title', 'Daftar')

@section('content')
    <h1 class="h4 fw-bold mb-1">Daftar</h1>
    <p class="text-body-secondary mb-4">Buat akun untuk mulai menyewa kendaraan.</p>

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">Nama lengkap</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                class="form-control @error('name') is-invalid @enderror" autocomplete="name" required autofocus>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Nomor WhatsApp</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="081234567890"
                class="form-control @error('phone') is-invalid @enderror" autocomplete="tel" required>
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Konfirmasi password</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                class="form-control" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Daftar</button>
    </form>

    <p class="text-center text-body-secondary mt-4 mb-0">
        Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
    </p>
@endsection
