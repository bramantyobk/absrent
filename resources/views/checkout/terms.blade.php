@extends('layouts.app')

@section('title', 'Syarat dan Ketentuan')

@section('content')
    <h1 class="page-title mb-3">Syarat dan ketentuan</h1>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">
                @foreach (collect($errors->all())->unique() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="summary-card mb-3">
        @foreach ($blocks as $block)
            @if ($block['type'] === 'heading')
                <h2 class="section-title fs-6 mt-3">{{ $block['text'] }}</h2>
            @else
                <p class="small" style="white-space: pre-line;">{{ $block['text'] }}</p>
            @endif
        @endforeach
    </div>

    <form method="POST" action="{{ route('checkout.store') }}" class="summary-card">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input @error('terms_accepted') is-invalid @enderror" id="terms_accepted" name="terms_accepted" value="1" required>
            <label for="terms_accepted" class="form-check-label">Saya telah membaca dan menyetujui syarat dan ketentuan di atas.</label>
        </div>

        <p class="small text-body-secondary">Pesanan baru dibuat setelah Anda menyetujui, lalu berstatus Pending sampai pembayaran dikonfirmasi.</p>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('checkout.show') }}" class="btn btn-outline-secondary">Kembali</a>
            <button type="submit" class="btn btn-primary">Setuju dan buat pesanan</button>
        </div>
    </form>
@endsection
