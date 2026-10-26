@extends('layouts.app')

@section('title', 'Syarat dan Ketentuan')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">
                @foreach (collect($errors->all())->unique() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="order-hero order-hero-booking mb-4" aria-label="Tahapan pesanan">
        <div class="order-hero-head">
            <div>
                <p class="order-hero-eyebrow mb-1"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>Langkah 2 dari 3</p>
                <h1 class="order-hero-code">Syarat dan ketentuan</h1>
                <p class="small mb-0 order-hero-muted">Baca perjanjian sewa di bawah, lalu setujui untuk membuat pesanan.</p>
            </div>
            <ol class="order-steps">
                @foreach (['Periksa pesanan', 'Syarat & ketentuan', 'Pembayaran'] as $index => $step)
                    <li @class(['is-done' => $index === 0, 'is-current' => $index === 1])>
                        <span class="order-step-dot">
                            @if ($index === 0)
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        <span class="order-step-label">{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <form method="POST" action="{{ route('checkout.store') }}" class="order-detail">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

        <div class="terms-summary">
            <div>
                <span class="terms-summary-label"><i class="bi bi-car-front me-1" aria-hidden="true"></i>Kendaraan</span>
                <strong>{{ $lines->map(fn (array $line): string => "{$line['vehicle']->brand} {$line['vehicle']->model}")->implode(', ') }}</strong>
            </div>
            <div>
                <span class="terms-summary-label"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Jadwal ({{ $days }} hari)</span>
                <strong>{{ $start->toDateIndonesian() }} &rarr; {{ $end->toDateIndonesian() }}</strong>
            </div>
            <div>
                <span class="terms-summary-label"><i class="bi bi-receipt me-1" aria-hidden="true"></i>Total</span>
                <strong>Rp{{ number_format($total, 0, ',', '.') }}</strong>
            </div>
        </div>

        <article class="terms-document" aria-label="Isi syarat dan ketentuan">
            @foreach ($blocks as $block)
                @if ($block['type'] === 'heading')
                    <h2>{{ $block['text'] }}</h2>
                @else
                    <p>{{ $block['text'] }}</p>
                @endif
            @endforeach
        </article>

        <div class="terms-consent">
            <label for="terms_accepted" class="terms-check">
                <input type="checkbox" class="form-check-input @error('terms_accepted') is-invalid @enderror" id="terms_accepted" name="terms_accepted" value="1" required>
                <span>Saya telah membaca dan menyetujui syarat dan ketentuan di atas.</span>
            </label>

            <p class="small text-body-secondary mt-3 mb-3">Pesanan baru dibuat setelah Anda menyetujui, lalu berstatus <strong>Pending</strong> sampai pembayaran dikonfirmasi.</p>

            <div class="d-flex flex-column-reverse flex-sm-row justify-content-sm-between gap-2">
                <a href="{{ route('checkout.show') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-2" aria-hidden="true"></i>Setuju dan buat pesanan</button>
            </div>
        </div>
    </form>
@endsection
