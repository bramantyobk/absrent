@extends('layouts.guest')

@section('title', 'Lupa Password')

@section('content')
    <h1 class="h4 fw-bold mb-1">Lupa password</h1>
    <p class="text-body-secondary mb-4">Masukkan email akun Anda. Admin akan mengirim tautan untuk mengatur ulang password ke WhatsApp Anda.</p>

    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">Ajukan reset password</button>
    </form>

    <p class="text-center text-body-secondary mt-4 mb-0">
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </p>
@endsection
