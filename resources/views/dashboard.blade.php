@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
    <div class="row g-4">
        <div class="col-xl-5">
            <div class="d-flex flex-column gap-4">
                <x-dashboard.doughnut-card id="topVehiclesChart" title="5 Top Orderan Rental" :url="route('dashboard.charts.top-vehicles')" />

                <x-dashboard.doughnut-card id="orderStatusChart" title="Status Pesanan" :url="route('dashboard.charts.order-status')" />

                <div class="card-custom p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ asset('images/icons/mark.svg') }}" alt="" width="16" height="16">
                            <h2 class="card-heading mb-0">Jumlah Kendaraan</h2>
                        </div>
                        <span class="fw-bold text-body-tertiary">{{ $vehicleStats['total'] }}</span>
                    </div>
                    <div class="d-flex flex-column gap-4">
                        <div class="detail-row">
                            <span>Tersedia</span>
                            <strong>{{ $vehicleStats['available'] }}</strong>
                        </div>
                        <hr class="divider">
                        <div class="detail-row">
                            <span>Jumlah bookingan</span>
                            <strong>{{ $vehicleStats['booked'] }}</strong>
                        </div>
                        <hr class="divider">
                        <div class="detail-row">
                            <span>Sedang disewa</span>
                            <strong>{{ $vehicleStats['rented'] }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="d-flex flex-column gap-4">
                <div class="card-custom p-4">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <img src="{{ asset('images/icons/mark.svg') }}" alt="" width="16" height="16">
                        <h2 class="card-heading mb-0">Pendapatan</h2>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-4">
                            <label for="revenueType" class="form-label fw-semibold">Tipe Kendaraan</label>
                            <select id="revenueType" class="form-select">
                                <option value="">Semua</option>
                                @foreach ($vehicleTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-sm-4">
                            <label for="revenueStart" class="form-label fw-semibold">Awal</label>
                            <input type="date" id="revenueStart" class="form-control" value="{{ $defaultStartDate }}">
                        </div>
                        <div class="col-6 col-sm-4">
                            <label for="revenueEnd" class="form-label fw-semibold">Akhir</label>
                            <input type="date" id="revenueEnd" class="form-control" value="{{ $defaultEndDate }}">
                        </div>
                    </div>

                    <div id="revenueError" class="alert alert-danger d-none" role="alert">
                        Grafik pendapatan gagal dimuat. Periksa rentang tanggal lalu coba lagi.
                    </div>

                    <div class="bar-chart-wrap mb-4">
                        <canvas data-chart="revenue" data-url="{{ route('dashboard.charts.revenue') }}" aria-label="Grafik pendapatan" role="img"></canvas>
                    </div>

                    <hr class="divider mb-4">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div>
                            <p class="total-label mb-1">Total Pendapatan</p>
                            <p class="total-sublabel mb-0">Pesanan lunas pada rentang tanggal</p>
                        </div>
                        <p class="total-value mb-0" id="revenueTotal">Rp0</p>
                    </div>
                </div>

                <div class="card-custom p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h2 class="card-heading mb-0">Pesanan Terakhir</h2>
                        @if (Route::has('dashboard.orders.index'))
                            <a href="{{ route('dashboard.orders.index') }}" class="link-see-all">Lihat semua</a>
                        @endif
                    </div>

                    @forelse ($recentOrders as $order)
                        @php
                            $firstItem = $order->items->first();
                            $vehicle = $firstItem?->vehicle;
                            $extra = $order->items->count() - 1;
                        @endphp

                        @unless ($loop->first)
                            <hr class="divider">
                        @endunless

                        <div class="transaction-item">
                            <div class="d-flex align-items-center gap-3 gap-sm-4">
                                <img src="{{ $vehicle?->photo ? asset('storage/'.$vehicle->photo) : asset('images/vehicle-placeholder.png') }}"
                                    alt="{{ $vehicle?->brand }} {{ $vehicle?->model }}">
                                <div>
                                    <p class="transaction-name mb-1">
                                        {{ $vehicle?->brand }} {{ $vehicle?->model }}
                                        @if ($extra > 0)
                                            <span class="text-body-tertiary fw-medium">+{{ $extra }} lainnya</span>
                                        @endif
                                    </p>
                                    <p class="transaction-type mb-0">{{ $vehicle?->type->label() }} &middot; {{ $order->code }}</p>
                                </div>
                            </div>
                            <div class="text-end">
                                <p class="transaction-date mb-1">{{ $order->created_at->locale('id')->translatedFormat('d M Y') }}</p>
                                <p class="transaction-price mb-1">{{ $order->formattedTotalPrice() }}</p>
                                <x-status-badge :status="$order->status" />
                            </div>
                        </div>
                    @empty
                        <p class="text-body-secondary mb-0">Belum ada pesanan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
