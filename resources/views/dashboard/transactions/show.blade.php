@extends('layouts.dashboard')

@section('title', "Transaksi {$transaction->order->code}")

@section('content')
    @include('dashboard.users._alerts')

    <a href="{{ route('dashboard.transactions.index') }}" class="small fw-semibold">&larr; Daftar transaksi</a>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
        <h2 class="card-heading mb-0">Transaksi {{ $transaction->order->code }}</h2>
        <span class="d-flex gap-1">
            <x-status-badge :status="$transaction->status" />
            @if ($transaction->isNearExpiry())
                <span class="status-badge status-batal">Hampir habis</span>
            @endif
        </span>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="card-custom p-4">
                <h3 class="card-heading fs-6">Detail</h3>
                <p class="fs-4 fw-bold mb-3">Rp{{ number_format($transaction->amount, 0, ',', '.') }}</p>
                <dl class="small mb-0">
                    <dt>Pesanan</dt>
                    <dd><a href="{{ route('dashboard.orders.show', $transaction->order) }}">{{ $transaction->order->code }}</a> ({{ $transaction->order->status->label() }})</dd>
                    <dt>Pelanggan</dt>
                    <dd>{{ $transaction->order->user->name }}<br><span class="text-body-secondary">{{ $transaction->order->user->phone }}</span></dd>
                    <dt>Rekening tujuan (dipilih pelanggan)</dt>
                    <dd>
                        @if ($transaction->bankAccount)
                            {{ $transaction->bankAccount->bank_name }} {{ $transaction->bankAccount->account_number }}<br>
                            <span class="text-body-secondary">a.n. {{ $transaction->bankAccount->account_holder }}</span>
                        @else
                            -
                        @endif
                    </dd>
                    <dt>Dikonfirmasi pelanggan</dt>
                    <dd>{{ $transaction->confirmed_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                    @if ($transaction->status === \App\TransactionStatus::Verifying)
                        <dt>Batas verifikasi</dt>
                        <dd>{{ $transaction->order->expires_at->format('d/m/Y H:i') }} {{ config('rental.timezone_label') }}</dd>
                    @endif
                    @if ($transaction->verified_at)
                        <dt>Diverifikasi</dt>
                        <dd>{{ $transaction->verifier?->name }}, {{ $transaction->verified_at->format('d/m/Y H:i') }}</dd>
                    @endif
                    @if ($transaction->note)
                        <dt>Catatan</dt>
                        <dd>{{ $transaction->note }}</dd>
                    @endif
                    @if ($transaction->proof_path)
                        <dt>Bukti pembayaran</dt>
                        <dd><a href="{{ route('dashboard.transactions.proof', $transaction) }}" target="_blank" rel="noopener">Lihat bukti</a></dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            @if ($canVerify)
                <form method="POST" action="{{ route('dashboard.transactions.verify', $transaction) }}" enctype="multipart/form-data" class="card-custom p-4" novalidate>
                    @csrf
                    @php($decision = old('status', 'paid'))

                    <h3 class="card-heading fs-6">Verifikasi pembayaran</h3>
                    <p class="small text-body-secondary">Periksa mutasi rekening, lalu tetapkan Lunas (wajib unggah bukti) atau Gagal (final, pelanggan harus membuat pesanan baru).</p>

                    @if ($errors->has('status') && ! $errors->has('proof') && ! $errors->has('note'))
                        <div class="alert alert-danger" role="alert">{{ $errors->first('status') }}</div>
                    @endif

                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input type="radio" class="form-check-input" name="status" id="status_paid" value="paid" data-verify-choice @checked($decision === 'paid')>
                            <label for="status_paid" class="form-check-label">Lunas</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="radio" class="form-check-input" name="status" id="status_failed" value="failed" data-verify-choice @checked($decision === 'failed')>
                            <label for="status_failed" class="form-check-label">Gagal</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="bank_account_id" class="form-label">Rekening tujuan</label>
                        <select id="bank_account_id" name="bank_account_id" class="form-select">
                            @foreach ($bankAccounts as $account)
                                <option value="{{ $account->id }}" @selected((int) old('bank_account_id', $transaction->bank_account_id) === $account->id)>
                                    {{ $account->bank_name }} {{ $account->account_number }}{{ $account->is_active ? '' : ' (nonaktif)' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Ubah bila pelanggan salah memilih rekening.</div>
                    </div>

                    <div class="mb-3" data-verify-section="paid">
                        <label for="proof" class="form-label">Bukti pembayaran</label>
                        <input type="file" id="proof" name="proof" accept="image/jpeg,image/png" class="form-control @error('proof') is-invalid @enderror">
                        <div class="form-text">JPG atau PNG, maksimal 2 MB. Disimpan privat.</div>
                        @error('proof')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" data-verify-section="failed">
                        <label for="note" class="form-label">Alasan Gagal</label>
                        <textarea id="note" name="note" rows="3" maxlength="1000" class="form-control @error('note') is-invalid @enderror">{{ old('note') }}</textarea>
                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan verifikasi</button>
                </form>
            @else
                <div class="card-custom p-4">
                    <p class="text-body-secondary mb-0">Transaksi ini sudah tidak menunggu verifikasi.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
