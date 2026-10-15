@extends('layouts.app')

@section('title', 'Pesanan')

@section('content')
    <h1 class="page-title mb-3">Ringkasan pesanan</h1>

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
        <div class="empty-state">
            <p class="mb-3">Belum ada kendaraan di pesanan Anda.</p>
            <a href="{{ route('vehicles.index') }}" class="btn btn-primary">Pilih kendaraan</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="summary-card mb-3">
                    <h2 class="section-title fs-6">Jadwal sewa</h2>
                    <dl class="row mb-3 small">
                        <dt class="col-5 col-md-3">Mulai</dt>
                        <dd class="col-7 col-md-9 mb-1">{{ $startLabel }}</dd>
                        <dt class="col-5 col-md-3">Kembali</dt>
                        <dd class="col-7 col-md-9 mb-1">{{ $endLabel }}</dd>
                        <dt class="col-5 col-md-3">Durasi</dt>
                        <dd class="col-7 col-md-9 mb-0">{{ $days }} hari</dd>
                    </dl>

                    @if ($canReschedule)
                        <form method="POST" action="{{ route('checkout.reschedule') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Ubah jadwal</button>
                        </form>
                    @else
                        <p class="small text-body-secondary mb-0">Untuk mengubah jadwal, hapus semua kendaraan lalu pilih jadwal baru.</p>
                    @endif
                </div>

                <div class="d-flex flex-column gap-3">
                    @foreach ($lines as $line)
                        <article class="order-line @if ($line['error']) order-line-invalid @endif">
                            <img src="{{ $line['vehicle']->photo_url }}" alt="" class="order-line-photo">
                            <div class="flex-grow-1">
                                <h2 class="order-line-title">{{ $line['vehicle']->brand }} {{ $line['vehicle']->model }}</h2>
                                @if ($line['error'])
                                    <p class="small text-danger mb-1">{{ $line['error'] }}</p>
                                @else
                                    <p class="small mb-1">
                                        {{ $line['days'] }} hari &times; Rp{{ number_format($line['vehicle']->price_per_day, 0, ',', '.') }}
                                        = <strong>Rp{{ number_format($line['subtotal'], 0, ',', '.') }}</strong>
                                    </p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('checkout.items.destroy', $line['index']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </article>
                    @endforeach
                </div>

                @if ($canAddMore)
                    <a href="{{ $addVehicleUrl }}" class="btn btn-outline-primary mt-3">Tambah kendaraan</a>
                    <p class="small text-body-secondary mt-2 mb-0">Hanya unit yang kosong pada jadwal yang sama yang ditampilkan.</p>
                @else
                    <p class="small text-body-secondary mt-3 mb-0">Satu pesanan maksimal {{ $maxVehicles }} kendaraan.</p>
                @endif
            </div>

            <div class="col-12 col-lg-4">
                <form method="POST" action="{{ route('checkout.store') }}" class="summary-card" id="checkoutForm">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                    <h2 class="section-title fs-6">Total</h2>
                    <p class="summary-total">Rp{{ number_format($total, 0, ',', '.') }}</p>
                    <p class="small text-body-secondary">
                        {{ $lines->count() }} kendaraan. Pesanan akan berstatus Pending sampai pembayaran dikonfirmasi.
                    </p>

                    @if ($hasInvalidLine)
                        <p class="small text-danger">Hapus item yang tidak valid sebelum membuat pesanan.</p>
                    @endif

                    <button type="submit" class="btn btn-primary w-100 summary-submit" @disabled($hasInvalidLine)>Buat Pesanan</button>
                </form>
            </div>
        </div>
    @endif
@endsection
