@extends('layouts.dashboard')

@section('title', 'Pendapatan')

@php
    $rupiah = fn (int $amount): string => ($amount < 0 ? '-' : '').'Rp'.number_format(abs($amount), 0, ',', '.');
@endphp

@section('content')
    @include('dashboard.users._alerts')

    <div class="card-dash-user p-4 mb-4">
        <h2 class="card-heading mb-1">Pendapatan</h2>
        <p class="small text-body-secondary mb-4">
            Pendapatan dicatat pada tanggal verifikasi pembayaran, dan selisih ganti kendaraan pada tanggal perubahan. Pesanan Refund, Dibatalkan, dan Gagal tidak dihitung.
        </p>

        <form method="GET" action="{{ route('dashboard.revenue.index') }}" class="row g-2">
            <div class="col-6 col-md-3">
                <label for="start_date" class="form-label">Dari tanggal</label>
                <input type="date" id="start_date" name="start_date" value="{{ $filters['start_date'] }}" class="form-control @error('start_date') is-invalid @enderror">
                @error('start_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="end_date" class="form-label">Sampai tanggal</label>
                <input type="date" id="end_date" name="end_date" value="{{ $filters['end_date'] }}" class="form-control @error('end_date') is-invalid @enderror">
                @error('end_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="type" class="form-label">Kategori</label>
                <select id="type" name="type" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary">Terapkan</button>
                <a href="{{ route('dashboard.revenue.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card-custom p-4 h-100">
                <p class="small text-body-secondary mb-1">Total pendapatan</p>
                <p class="fs-3 fw-bold mb-0">{{ $rupiah($summary['total']) }}</p>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card-custom p-4 h-100">
                <p class="small text-body-secondary mb-1">Dari sewa (subtotal)</p>
                <p class="fs-5 fw-semibold mb-0">{{ $rupiah($summary['base']) }}</p>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card-custom p-4 h-100">
                <p class="small text-body-secondary mb-1">Selisih ganti kendaraan</p>
                <p class="fs-5 fw-semibold mb-0">{{ $rupiah($summary['delta']) }}</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xxl-6">
            <div class="card-dash-user p-4 h-100">
                <h3 class="card-heading fs-6">Per hari</h3>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 users-table">
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col" class="text-end">Subtotal</th>
                                <th scope="col" class="text-end">Selisih</th>
                                <th scope="col" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($days as $day)
                                <tr>
                                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($day['day'])->format('d/m/Y') }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($day['base']) }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($day['delta']) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ $rupiah($day['total']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary py-5">Belum ada pendapatan pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-6">
            <div class="card-dash-user p-4 h-100">
                <h3 class="card-heading fs-6">Per kendaraan</h3>
                <p class="small text-body-secondary">Nilai pesanan dikreditkan ke unit yang sedang terpasang (unit pengganti bila pernah diganti).</p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 users-table">
                        <thead>
                            <tr>
                                <th scope="col">Kendaraan</th>
                                <th scope="col" class="text-end">Subtotal</th>
                                <th scope="col" class="text-end">Selisih</th>
                                <th scope="col" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vehicles as $vehicle)
                                <tr>
                                    <td>
                                        <a href="{{ route('dashboard.vehicles.show', $vehicle['vehicle_id']) }}" class="fw-semibold text-decoration-none">{{ $vehicle['name'] }}</a>
                                        <div class="small text-body-secondary">{{ $vehicle['plate_number'] }}</div>
                                    </td>
                                    <td class="text-end text-nowrap">{{ $rupiah($vehicle['base']) }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($vehicle['delta']) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ $rupiah($vehicle['total']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary py-5">Belum ada pendapatan pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card-dash-user p-4">
                <h3 class="card-heading fs-6">Selisih ganti kendaraan</h3>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 users-table">
                        <thead>
                            <tr>
                                <th scope="col">Tanggal</th>
                                <th scope="col">Pesanan</th>
                                <th scope="col">Penggantian</th>
                                <th scope="col" class="text-end">Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($changes as $change)
                                <tr>
                                    <td class="text-nowrap">{{ $change->created_at->format('d/m/Y H:i') }}</td>
                                    <td><a href="{{ route('dashboard.orders.show', $change->orderItem->order) }}">{{ $change->orderItem->order->code }}</a></td>
                                    <td>{{ $change->fromVehicle->brand }} {{ $change->fromVehicle->model }} &rarr; {{ $change->toVehicle->brand }} {{ $change->toVehicle->model }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($change->final_amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary py-5">Tidak ada penggantian kendaraan pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
