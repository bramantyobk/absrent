@extends('layouts.app')

@section('title', "Pembayaran {$order->code}")

@section('content')
    <a href="{{ route('orders.show', $order) }}" class="small fw-semibold"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Detail pesanan</a>

    @if ($errors->has('order'))
        <div class="alert alert-danger mt-3" role="alert">{{ $errors->first('order') }}</div>
    @endif

    <section class="order-hero order-hero-pending my-3 mb-4" aria-label="Tahapan pembayaran">
        <div class="order-hero-head mb-3">
            <div>
                <p class="order-hero-eyebrow mb-1"><i class="bi bi-credit-card me-1" aria-hidden="true"></i>Langkah 3 dari 3</p>
                <h1 class="order-hero-code">Pembayaran</h1>
                <p class="small mb-0 order-hero-muted">Pesanan {{ $order->code }}</p>
            </div>
            <ol class="order-steps">
                @foreach (['Periksa pesanan', 'Syarat & ketentuan', 'Pembayaran'] as $index => $step)
                    <li @class(['is-done' => $index < 2, 'is-current' => $index === 2])>
                        <span class="order-step-dot">
                            @if ($index < 2)
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        <span class="order-step-label">{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
            <div class="order-hero-chip">
                <span class="order-hero-chip-label">Pembayaran</span>
                <x-status-badge :status="$order->transaction->status" />
            </div>
        </div>

        @if ($order->expires_at)
            <p class="order-hero-hint"><i class="bi bi-hourglass-split me-2 order-hero-tone" aria-hidden="true"></i>Konfirmasi sebelum <strong>{{ $order->expires_at->toLongIndonesian() }}</strong>, atau pesanan dibatalkan otomatis.</p>
        @endif
    </section>

    <section class="order-detail" aria-label="Pembayaran dan ringkasan pesanan">
        <div class="order-detail-grid">
            <div class="order-detail-main">
                <form method="POST" action="{{ route('orders.confirm', $order) }}" id="confirm-payment-form" target="_blank" data-payment-confirm
                    data-after-url="{{ route('orders.show', $order) }}">
                    @csrf

                    <h2 class="section-title fs-6">Transfer ke salah satu rekening</h2>
                    <ol class="pay-howto">
                        <li><span>1</span>Transfer sesuai total pembayaran</li>
                        <li><span>2</span>Pilih rekening yang Anda gunakan</li>
                        <li><span>3</span>Tekan <strong>Konfirmasi pembayaran</strong></li>
                    </ol>
                    <p class="small text-body-secondary">Anda tidak perlu mengunggah bukti, operator akan memeriksa pembayaran Anda.</p>

                    @forelse ($bankAccounts as $account)
                        <label for="bank_{{ $account->id }}" class="bank-option">
                            <input type="radio" class="form-check-input" name="bank_account_id" id="bank_{{ $account->id }}" value="{{ $account->id }}"
                                @checked((int) old('bank_account_id') === $account->id) required>
                            <i class="bi bi-bank2 bank-option-icon" aria-hidden="true"></i>
                            <span class="bank-option-body">
                                <span class="fw-semibold">{{ $account->bank_name }}</span>
                                <span class="bank-option-number">{{ $account->account_number }}</span>
                                <span class="small text-body-secondary">a.n. {{ $account->account_holder }}</span>
                            </span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="{{ preg_replace('/\s+/', '', $account->account_number) }}" aria-label="Salin nomor rekening {{ $account->bank_name }}">
                                <i class="bi bi-copy me-1" aria-hidden="true"></i><span>Salin</span>
                            </button>
                        </label>
                    @empty
                        <div class="alert alert-warning mb-0" role="alert">
                            Rekening tujuan belum tersedia. Hubungi kami lewat WhatsApp di bawah.
                        </div>
                    @endforelse

                    @error('bank_account_id')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </form>

                <h2 class="section-title fs-6 mt-4">Pesanan Anda</h2>
                <div class="d-flex flex-column gap-3">
                    @foreach ($order->items as $item)
                        <article class="pay-item">
                            <img src="{{ $item->vehicle->photo_url }}" alt="" class="pay-item-photo">
                            <div class="flex-grow-1">
                                <h3 class="order-vehicle-title fs-6">{{ $item->vehicle->brand }} {{ $item->vehicle->model }}</h3>
                                <p class="small text-body-secondary mb-0">
                                    {{ $item->start_at->toLongIndonesian() }} &ndash; {{ $item->end_at->toLongIndonesian() }}
                                    ({{ $item->days }} hari)
                                </p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <aside class="order-detail-side">
                <div class="order-side-section">
                    <h2 class="section-title fs-6">Total yang harus ditransfer</h2>
                    <div class="order-receipt">
                        @foreach ($order->items as $item)
                            <div><span>{{ $item->vehicle->brand }} {{ $item->vehicle->model }} &middot; {{ $item->days }} hari</span><span>Rp{{ number_format($item->subtotal, 0, ',', '.') }}</span></div>
                        @endforeach
                    </div>
                    <div class="order-receipt-total">
                        <span>Total</span>
                        <strong>{{ $order->formattedTotalPrice() }}</strong>
                    </div>

                    <button type="submit" form="confirm-payment-form" class="btn btn-primary w-100 mt-3 mb-2 summary-submit" @disabled($bankAccounts->isEmpty())>
                        <i class="bi bi-check2-circle me-2" aria-hidden="true"></i>Konfirmasi pembayaran
                    </button>

                    <form method="POST" action="{{ route('orders.cancel', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Batalkan pesanan ini?')">Batalkan pesanan</button>
                    </form>
                </div>
            </aside>
        </div>
    </section>
@endsection
