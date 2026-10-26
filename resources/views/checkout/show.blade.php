@extends('layouts.app')

@section('title', 'Pesanan')

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

    @if ($lines->isEmpty())
        <h1 class="page-title mb-3">Ringkasan pesanan</h1>
        <div class="empty-state">
            <p class="mb-3">Belum ada kendaraan di pesanan Anda.</p>
            <a href="{{ route('vehicles.index') }}" class="btn btn-primary">Pilih kendaraan</a>
        </div>
    @else
        <section class="order-hero order-hero-booking mb-4" aria-label="Jadwal sewa dan tahapan pesanan">
            <div class="order-hero-head mb-4">
                <div>
                    <p class="order-hero-eyebrow mb-1"><i class="bi bi-bag-check me-1" aria-hidden="true"></i>Langkah 1 dari 3</p>
                    <h1 class="order-hero-code">Ringkasan pesanan</h1>
                    <p class="small mb-0 order-hero-muted">Periksa kendaraan dan jadwal sebelum membuat pesanan.</p>
                </div>
                <ol class="order-steps">
                    @foreach (['Periksa pesanan', 'Syarat & ketentuan', 'Pembayaran'] as $index => $step)
                        <li @class(['is-current' => $index === 0])>
                            <span class="order-step-dot">{{ $index + 1 }}</span>
                            <span class="order-step-label">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
                <div>
                    @if ($canReschedule)
                        <form method="POST" action="{{ route('checkout.reschedule') }}">
                            @csrf
                            <button type="submit" class="btn btn-light btn-sm order-hero-btn"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Ubah jadwal</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="order-vehicle-schedule mb-4">
                <div>
                    <span class="order-vehicle-when"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Mulai</span>
                    <strong>{{ $start->toDateIndonesian() }}</strong>
                    <span>pukul {{ $start->format('H:i') }} {{ config('rental.timezone_label') }}</span>
                </div>
                <i class="bi bi-arrow-right order-vehicle-arrow" aria-hidden="true"></i>
                <div>
                    <span class="order-vehicle-when"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Kembali</span>
                    <strong>{{ $end->toDateIndonesian() }}</strong>
                    <span>pukul {{ $end->format('H:i') }} {{ config('rental.timezone_label') }}</span>
                </div>
            </div>

            @unless ($canReschedule)
                <p class="order-hero-hint mt-4"><i class="bi bi-info-circle-fill me-2 order-hero-tone" aria-hidden="true"></i>Durasi {{ $days }} hari. Untuk mengubah jadwal, hapus semua kendaraan lalu pilih jadwal baru.</p>
            @endunless
        </section>

        <section class="order-detail" aria-label="Kendaraan dan ringkasan pembayaran">
            <div class="order-detail-grid">
                <div class="order-detail-main">
                    <h2 class="section-title fs-6">Kendaraan <span class="text-body-secondary fw-normal">({{ $lines->count() }})</span></h2>

                    <div class="d-flex flex-column gap-4">
                        @foreach ($lines as $line)
                            <article @class(['order-vehicle', 'order-vehicle-invalid' => $line['error']])>
                                <div class="order-vehicle-media">
                                    <img src="{{ $line['vehicle']->photo_url }}" alt="{{ $line['vehicle']->brand }} {{ $line['vehicle']->model }}">
                                </div>
                                <div class="order-vehicle-body">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                        <div>
                                            <div class="d-flex flex-wrap gap-1 mb-2">
                                                <span class="vehicle-chip"><i class="bi bi-{{ $line['vehicle']->type->value === 'car' ? 'car-front' : 'scooter' }} me-1" aria-hidden="true"></i>{{ $line['vehicle']->type->label() }}</span>
                                                @if ($line['vehicle']->drive_type)
                                                    <span class="vehicle-chip">{{ $line['vehicle']->drive_type->label() }}</span>
                                                @endif
                                                <span class="vehicle-chip"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ $line['days'] }} hari</span>
                                            </div>
                                            <h3 class="order-vehicle-title">{{ $line['vehicle']->brand }} {{ $line['vehicle']->model }}</h3>
                                            <p class="small text-body-secondary mb-0">{{ $line['vehicle']->year }} · {{ $line['vehicle']->color }}</p>
                                        </div>
                                        @unless ($line['error'])
                                            <div class="order-vehicle-price">
                                                <strong>Rp{{ number_format($line['subtotal'], 0, ',', '.') }}</strong>
                                                <span>{{ $line['days'] }} hari &times; Rp{{ number_format($line['vehicle']->price_per_day, 0, ',', '.') }}</span>
                                            </div>
                                        @endunless
                                    </div>

                                    @if ($line['error'])
                                        <p class="small text-danger fw-semibold mt-2 mb-0"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ $line['error'] }}</p>
                                    @endif

                                    <form method="POST" action="{{ route('checkout.items.destroy', $line['index']) }}" class="mt-3">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-2" aria-hidden="true"></i>Hapus</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($canAddMore)
                        <a href="{{ $addVehicleUrl }}" class="order-add-vehicle mt-4">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            <span>Tambah kendaraan</span>
                            <small>Hanya unit yang kosong pada jadwal yang sama yang ditampilkan.</small>
                        </a>
                    @else
                        <p class="small text-body-secondary mt-4 mb-0">Satu pesanan maksimal {{ $maxVehicles }} kendaraan.</p>
                    @endif
                </div>

                <aside class="order-detail-side" id="checkoutForm">
                    <div class="order-side-section">
                        <h2 class="section-title fs-6">Ringkasan pembayaran</h2>
                        <div class="order-receipt">
                            @foreach ($lines as $line)
                                @unless ($line['error'])
                                    <div><span>{{ $line['vehicle']->brand }} {{ $line['vehicle']->model }} &middot; {{ $line['days'] }} hari</span><span>Rp{{ number_format($line['subtotal'], 0, ',', '.') }}</span></div>
                                @endunless
                            @endforeach
                        </div>
                        <div class="order-receipt-total">
                            <span>Total</span>
                            <strong>Rp{{ number_format($total, 0, ',', '.') }}</strong>
                        </div>

                        <p class="small text-body-secondary mt-3">
                            {{ $lines->count() }} kendaraan. Anda akan diminta menyetujui syarat dan ketentuan sebelum pesanan dibuat.
                        </p>

                        @if ($hasInvalidLine)
                            <p class="small text-danger">Hapus item yang tidak valid sebelum membuat pesanan.</p>
                            <button type="button" class="btn btn-primary w-100 summary-submit d-none d-lg-block" disabled>Buat Pesanan</button>
                        @else
                            <a href="{{ route('checkout.terms') }}" class="btn btn-primary w-100 summary-submit d-none d-lg-flex align-items-center justify-content-center">Buat Pesanan <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
                        @endif
                    </div>
                </aside>
            </div>
        </section>

        {{-- Mobile: total dan tombol menempel di bawah layar agar mudah dijangkau --}}
        <div class="checkout-bar d-lg-none">
            <div>
                <span class="small text-body-secondary d-block">Total</span>
                <strong>Rp{{ number_format($total, 0, ',', '.') }}</strong>
            </div>
            @if ($hasInvalidLine)
                <button type="button" class="btn btn-primary summary-submit" disabled>Buat Pesanan</button>
            @else
                <a href="{{ route('checkout.terms') }}" class="btn btn-primary summary-submit d-inline-flex align-items-center">Buat Pesanan <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
            @endif
        </div>
    @endif
@endsection
