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
                <div class="d-flex flex-column gap-3">
                    @foreach ($lines as $line)
                        <article class="order-line @if ($line['error']) order-line-invalid @endif">
                            <img src="{{ $line['vehicle']->photo_url }}" alt="" class="order-line-photo">
                            <div class="flex-grow-1">
                                <h2 class="order-line-title">{{ $line['vehicle']->brand }} {{ $line['vehicle']->model }}</h2>
                                <p class="small text-body-secondary mb-1">
                                    {{ \Illuminate\Support\Carbon::parse($line['start_at'])->format('d/m/Y H:i') }}
                                    &ndash;
                                    {{ \Illuminate\Support\Carbon::parse($line['end_at'])->format('d/m/Y H:i') }}
                                </p>
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

                <a href="{{ route('vehicles.index') }}" class="btn btn-outline-primary mt-3">Tambah kendaraan</a>
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
                        <p class="small text-danger">Hapus atau ubah item yang tidak valid sebelum membuat pesanan.</p>
                    @endif

                    <button type="submit" class="btn btn-primary w-100 summary-submit" @disabled($hasInvalidLine)>Buat Pesanan</button>
                </form>
            </div>
        </div>
    @endif
@endsection
