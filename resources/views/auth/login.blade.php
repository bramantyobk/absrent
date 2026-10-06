@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <h1 class="h4 fw-bold mb-1">Masuk</h1>
    <p class="text-body-secondary mb-4">Masuk untuk menyewa kendaraan.</p>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check mb-4">
            <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
            <label for="remember" class="form-check-label">Ingat saya</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">Masuk</button>
    </form>

    <p class="text-center text-body-secondary mt-4 mb-0">
        Belum punya akun? <a href="{{ route('register') }}">Daftar</a>
    </p>
@endsection
