@extends('layouts.app')

@section('title', "Pesanan {$order->code}")

@php
    $steps = ['Dibuat', 'Pembayaran', 'Booking', 'Disewa', 'Selesai'];
    $currentStep = $order->progressStep();
    $cancellation = $order->cancellationRequests->sortByDesc('id')->first();
    $hasPendingCancellation = $cancellation?->status === \App\CancellationStatus::Pending;
    $statusHint = match ($order->status) {
        \App\OrderStatus::Pending => $order->transaction?->confirmed_at
            ? 'Pembayaran sudah dikonfirmasi dan menunggu verifikasi operator.'
            : 'Selesaikan pembayaran sebelum batas waktu agar pesanan tidak dibatalkan otomatis.',
        \App\OrderStatus::Booking => 'Pembayaran lunas. Ambil kendaraan sesuai jadwal di bawah.',
        \App\OrderStatus::Rented => 'Kendaraan sedang Anda sewa. Kembalikan sesuai jadwal.',
        \App\OrderStatus::Completed => 'Sewa selesai. Terima kasih telah memakai layanan kami.',
        \App\OrderStatus::Refunded => 'Pembatalan disetujui. Dana dikembalikan oleh operator di luar aplikasi.',
        \App\OrderStatus::Cancelled => 'Pesanan dibatalkan dan jadwal kendaraan sudah dilepas.',
        \App\OrderStatus::Failed => 'Pembayaran tidak dapat diverifikasi, sehingga pesanan tidak dilanjutkan.',
        default => null,
    };
@endphp

@section('content')
    <a href="{{ route('orders.index') }}" class="small fw-semibold"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Pesanan saya</a>

    <section class="order-hero order-hero-{{ $order->status->value }} my-3 mb-4" aria-label="Status dan progres pesanan">
        <div class="order-hero-head mb-3">
            <div>
                <p class="order-hero-eyebrow mb-1"><i class="bi bi-receipt me-1" aria-hidden="true"></i>Kode pesanan</p>
                <h1 class="order-hero-code">{{ $order->code }}</h1>
                <p class="small mb-0 order-hero-muted">Dibuat {{ $order->created_at->toLongIndonesian() }}</p>
            </div>
            @if ($currentStep !== null)
                <ol class="order-steps">
                    @foreach ($steps as $index => $step)
                        <li @class(['is-done' => $index < $currentStep || $currentStep === 4, 'is-current' => $index === $currentStep && $currentStep !== 4])>
                            <span class="order-step-dot">
                                @if ($index < $currentStep || $currentStep === 4)
                                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>
                            <span class="order-step-label">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
            <div class="order-hero-chips">
                <div class="order-hero-chip">
                    <span class="order-hero-chip-label">Status pesanan</span>
                    <x-status-badge :status="$order->status" />
                </div>
                @if ($order->transaction)
                    <div class="order-hero-chip">
                        <span class="order-hero-chip-label">Pembayaran</span>
                        <x-status-badge :status="$order->transaction->status" />
                    </div>
                @endif
            </div>
        </div>

        @if ($currentStep === null && ! $statusHint)
            <p class="order-hero-tone fw-semibold mb-1"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Pesanan {{ strtolower($order->status->label()) }}</p>
        @endif

        @if ($statusHint)
            <p class="order-hero-hint"><i class="bi bi-info-circle-fill me-2 order-hero-tone" aria-hidden="true"></i>{{ $statusHint }}</p>
        @endif
        @if ($order->status_note)
            <p class="small mb-0 mt-1">{{ $order->status_note }}</p>
        @endif
        @if ($order->transaction?->note)
            <p class="small order-hero-muted mb-0 mt-1">{{ $order->transaction->note }}</p>
        @endif
    </section>

    @php
        $itemsTotal = (int) $order->items->sum('subtotal');
        $adjustment = (int) $order->total_price - $itemsTotal;
    @endphp

    <section class="order-detail" aria-label="Rincian pesanan">
        <div class="order-detail-grid">
            <div class="order-detail-main">
                <h2 class="section-title fs-6">Kendaraan <span class="text-body-secondary fw-normal">({{ $order->items->count() }})</span></h2>
                <div class="d-flex flex-column gap-4">
                    @foreach ($order->items as $item)
                        <article class="order-vehicle">
                            <div class="order-vehicle-media">
                                <img src="{{ $item->vehicle->photo_url }}" alt="{{ $item->vehicle->brand }} {{ $item->vehicle->model }}">
                            </div>
                            <div class="order-vehicle-body">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                    <div>
                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                            <span class="vehicle-chip"><i class="bi bi-{{ $item->vehicle->type->value === 'car' ? 'car-front' : 'scooter' }} me-1" aria-hidden="true"></i>{{ $item->vehicle->type->label() }}</span>
                                            @if ($item->vehicle->drive_type)
                                                <span class="vehicle-chip">{{ $item->vehicle->drive_type->label() }}</span>
                                            @endif
                                            <span class="vehicle-chip"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ $item->days }} hari</span>
                                        </div>
                                        <h3 class="order-vehicle-title">{{ $item->vehicle->brand }} {{ $item->vehicle->model }}</h3>
                                        <p class="small text-body-secondary mb-0">{{ $item->vehicle->year }} · {{ $item->vehicle->color }}</p>
                                    </div>
                                    <div class="order-vehicle-price">
                                        <strong>Rp{{ number_format($item->subtotal, 0, ',', '.') }}</strong>
                                        <span>{{ $item->days }} hari &times; Rp{{ number_format($item->unit_price, 0, ',', '.') }}</span>
                                    </div>
                                </div>

                                <div class="order-vehicle-schedule">
                                    <div>
                                        <span class="order-vehicle-when"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Mulai</span>
                                        <strong>{{ $item->start_at->toDateIndonesian() }}</strong>
                                        <span>pukul {{ $item->start_at->format('H:i') }} {{ config('rental.timezone_label') }}</span>
                                    </div>
                                    <i class="bi bi-arrow-right order-vehicle-arrow" aria-hidden="true"></i>
                                    <div>
                                        <span class="order-vehicle-when"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Kembali</span>
                                        <strong>{{ $item->end_at->toDateIndonesian() }}</strong>
                                        <span>pukul {{ $item->end_at->format('H:i') }} {{ config('rental.timezone_label') }}</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <aside class="order-detail-side">
                <div class="order-side-section">
                    <h2 class="section-title fs-6">Ringkasan pembayaran</h2>
                    <div class="order-receipt">
                        @foreach ($order->items as $item)
                            <div><span>{{ $item->vehicle->brand }} {{ $item->vehicle->model }} &middot; {{ $item->days }} hari</span><span>Rp{{ number_format($item->subtotal, 0, ',', '.') }}</span></div>
                        @endforeach
                        @if ($adjustment !== 0)
                            <div><span>Penyesuaian ganti kendaraan</span><span>{{ $adjustment > 0 ? '+' : '-' }}Rp{{ number_format(abs($adjustment), 0, ',', '.') }}</span></div>
                        @endif
                    </div>
                    <div class="order-receipt-total">
                        <span>Total</span>
                        <strong>{{ $order->formattedTotalPrice() }}</strong>
                    </div>

                    <dl class="small mt-3 mb-0">
                        @if ($order->expires_at && $order->status === \App\OrderStatus::Pending)
                            <dt>{{ $order->transaction?->confirmed_at ? 'Batas verifikasi' : 'Batas pembayaran' }}</dt>
                            <dd>{{ $order->expires_at->toLongIndonesian() }}</dd>
                        @endif
                        @if ($order->transaction?->confirmed_at)
                            <dt>Dikonfirmasi</dt>
                            <dd>{{ $order->transaction->confirmed_at->toLongIndonesian() }}</dd>
                        @endif
                    </dl>

                    @can('pay', $order)
                        <a href="{{ route('orders.payment', $order) }}" class="btn btn-primary w-100 mt-3 mb-2"><i class="bi bi-credit-card me-2" aria-hidden="true"></i>Bayar sekarang</a>
                        <form method="POST" action="{{ route('orders.cancel', $order) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Batalkan pesanan ini?')">Batalkan pesanan</button>
                        </form>
                    @endcan

                    @can('resendConfirmation', $order)
                        <p class="small text-body-secondary mt-3">Menunggu verifikasi operator.</p>
                        <a href="{{ route('orders.whatsapp', $order) }}" target="_blank" rel="noopener" class="btn btn-primary w-100"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Konfirmasi Pembayaran</a>
                    @endcan

                    @if ($order->transaction?->proof_path)
                        <a href="{{ route('orders.proof', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100 mt-3"><i class="bi bi-file-earmark-image me-2" aria-hidden="true"></i>Lihat bukti pembayaran</a>
                    @endif
                </div>

                @if ($cancellation)
                    <div class="order-side-section">
                        <h2 class="section-title fs-6">Pengajuan pembatalan: {{ $cancellation->status->label() }}</h2>
                        <p class="small text-body-secondary mb-0">{{ $cancellation->reason }}</p>
                        @if ($cancellation->handled_note)
                            <p class="small mt-2 mb-0">{{ $cancellation->handled_note }}</p>
                        @endif
                        @if ($hasPendingCancellation)
                            <a href="{{ route('orders.cancellation-whatsapp', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm w-100 mt-3"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Buka WhatsApp lagi</a>
                        @endif
                    </div>
                @endif

                @can('requestCancellation', $order)
                    <form method="POST" action="{{ route('orders.cancellation-request', $order) }}" class="order-side-section" target="_blank" data-payment-confirm
                        data-after-url="{{ route('orders.show', $order) }}">
                        @csrf
                        <h2 class="section-title fs-6">Ajukan pembatalan</h2>
                        <p class="small text-body-secondary">Pesanan sudah lunas, jadi pembatalan dikonfirmasi operator lewat WhatsApp. Dana dikembalikan di luar aplikasi.</p>
                        <label for="reason" class="form-label small">Alasan pembatalan</label>
                        <textarea id="reason" name="reason" rows="3" minlength="5" maxlength="1000" class="form-control @error('reason') is-invalid @enderror"
                            placeholder="Ceritakan singkat alasan Anda" required>{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn btn-outline-danger w-100 mt-3"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Ajukan Pembatalan</button>
                    </form>
                @endcan
            </aside>
        </div>

        @if ($supportUrl)
            <div class="order-detail-footer">
                <span><i class="bi bi-headset me-2" aria-hidden="true"></i>Ada pertanyaan tentang pesanan ini?</span>
                <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Butuh bantuan? WhatsApp</a>
            </div>
        @endif
    </section>
@endsection
