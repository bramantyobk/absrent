@extends('layouts.app')

@section('title', "Pembayaran {$order->code}")

@section('content')
    <a href="{{ route('orders.show', $order) }}" class="small fw-semibold">&larr; Detail pesanan</a>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
        <h1 class="page-title mb-0">Pembayaran {{ $order->code }}</h1>
        <x-status-badge :status="$order->transaction->status" />
    </div>

    @if ($errors->has('order'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('order') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <form method="POST" action="{{ route('orders.confirm', $order) }}" id="confirm-payment-form" novalidate>
                @csrf

                <div class="summary-card mb-3">
                    <h2 class="section-title fs-6">Transfer ke salah satu rekening</h2>
                    <p class="small text-body-secondary">
                        Transfer sesuai total, lalu pilih rekening yang Anda gunakan dan tekan Konfirmasi pembayaran. Anda tidak perlu mengunggah bukti,
                        operator akan memeriksa pembayaran Anda.
                    </p>

                    @forelse ($bankAccounts as $account)
                        <div class="form-check border rounded p-3 ps-5 mb-2">
                            <input type="radio" class="form-check-input" name="bank_account_id" id="bank_{{ $account->id }}" value="{{ $account->id }}"
                                @checked((int) old('bank_account_id') === $account->id) required>
                            <label for="bank_{{ $account->id }}" class="form-check-label d-block">
                                <span class="fw-semibold">{{ $account->bank_name }}</span><br>
                                <span class="fs-5">{{ $account->account_number }}</span><br>
                                <span class="small text-body-secondary">a.n. {{ $account->account_holder }}</span>
                            </label>
                        </div>
                    @empty
                        <div class="alert alert-warning mb-0" role="alert">
                            Rekening tujuan belum tersedia. Hubungi kami lewat WhatsApp di bawah.
                        </div>
                    @endforelse

                    @error('bank_account_id')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </form>

            <div class="d-flex flex-column gap-3">
                @foreach ($order->items as $item)
                    <article class="order-line">
                        <img src="{{ $item->vehicle->photo_url }}" alt="" class="order-line-photo">
                        <div class="flex-grow-1">
                            <h2 class="order-line-title">{{ $item->vehicle->brand }} {{ $item->vehicle->model }}</h2>
                            <p class="small text-body-secondary mb-0">
                                {{ $item->start_at->format('d/m/Y H:i') }} &ndash; {{ $item->end_at->format('d/m/Y H:i') }} {{ config('rental.timezone_label') }}
                                ({{ $item->days }} hari)
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="summary-card">
                <h2 class="section-title fs-6">Total yang harus ditransfer</h2>
                <p class="summary-total">{{ $order->formattedTotalPrice() }}</p>
                @if ($order->expires_at)
                    <p class="small text-body-secondary">Konfirmasi sebelum {{ $order->expires_at->format('d/m/Y H:i') }} {{ config('rental.timezone_label') }}, atau pesanan dibatalkan otomatis.</p>
                @endif

                <button type="submit" form="confirm-payment-form" class="btn btn-primary w-100 mb-2" @disabled($bankAccounts->isEmpty())>
                    Konfirmasi pembayaran
                </button>

                <form method="POST" action="{{ route('orders.cancel', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Batalkan pesanan ini?')">Batalkan pesanan</button>
                </form>
            </div>
        </div>
    </div>
@endsection
