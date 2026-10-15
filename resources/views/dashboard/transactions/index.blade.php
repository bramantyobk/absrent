@extends('layouts.dashboard')

@section('title', 'Transaksi')

@section('content')
    @include('dashboard.users._alerts')

    <div class="card-dash-user p-4">
        <h2 class="card-heading mb-1">Transaksi</h2>
        <p class="small text-body-secondary mb-4">Transaksi yang menunggu verifikasi tampil paling atas. Verifikasi sebelum batas waktu habis, atau transaksi menjadi Gagal otomatis.</p>

        <form method="GET" action="{{ route('dashboard.transactions.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-md-6">
                <label for="search" class="visually-hidden">Cari</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                    class="form-control" placeholder="Cari kode pesanan atau pelanggan">
            </div>
            <div class="col-12 col-md-6">
                <label for="status" class="visually-hidden">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">Terapkan</button>
                <a href="{{ route('dashboard.transactions.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table">
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Nominal</th>
                        <th scope="col">Rekening</th>
                        <th scope="col">Dikonfirmasi</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $transaction->order->code }}</td>
                            <td>{{ $transaction->order->user->name }}</td>
                            <td class="text-nowrap">Rp{{ number_format($transaction->amount, 0, ',', '.') }}</td>
                            <td class="small">{{ $transaction->bankAccount ? $transaction->bankAccount->bank_name.' '.$transaction->bankAccount->account_number : '-' }}</td>
                            <td class="small text-nowrap">{{ $transaction->confirmed_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <x-status-badge :status="$transaction->status" />
                                    @if ($transaction->isNearExpiry())
                                        <span class="status-badge status-batal">Hampir habis</span>
                                    @endif
                                </div>
                                @if ($transaction->status === \App\TransactionStatus::Verifying)
                                    <div class="small text-body-secondary mt-1">Batas {{ $transaction->order->expires_at->format('d/m H:i') }}</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('dashboard.transactions.show', $transaction) }}" class="btn btn-sm {{ $transaction->status === \App\TransactionStatus::Verifying ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    {{ $transaction->status === \App\TransactionStatus::Verifying ? 'Verifikasi' : 'Detail' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-5">Tidak ada transaksi yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transactions->hasPages())
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <p class="small text-body-secondary mb-0">
                    Menampilkan {{ $transactions->firstItem() }}-{{ $transactions->lastItem() }} dari {{ $transactions->total() }} transaksi
                </p>
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
@endsection
