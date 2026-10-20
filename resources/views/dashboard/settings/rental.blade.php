@extends('layouts.dashboard')

@section('title', 'Pengaturan Sewa')

@php
    $groups = [
        'Pengembalian dan jadwal' => ['return_buffer_hours', 'min_free_window_hours'],
        'Durasi sewa' => ['min_rental_hours', 'max_rental_days'],
        'Pembayaran dan verifikasi' => ['pending_expiry_hours', 'confirm_validation_window_hours', 'verification_warning_hours'],
        'Batas pesanan' => ['max_pending_orders', 'max_vehicles_per_order'],
        'Ganti kendaraan' => ['vehicle_change_admin_threshold'],
    ];
@endphp

@section('content')
    @include('dashboard.users._alerts')

    <form method="POST" action="{{ route('dashboard.settings.rental.update') }}" data-rental-settings novalidate>
        @csrf
        @method('PUT')

        <div class="alert alert-warning" role="alert">
            Perubahan berlaku untuk pesanan berikutnya. Batas waktu Pending dan verifikasi pesanan yang sudah dibuat tidak berubah,
            sedangkan jeda pengembalian langsung memengaruhi pengecekan ketersediaan, termasuk pesanan yang sudah ada.
        </div>

        @foreach ($groups as $heading => $keys)
            <div class="card-custom p-4 mb-4">
                <h2 class="card-heading">{{ $heading }}</h2>

                <div class="row g-3">
                    @foreach ($keys as $key)
                        @php($field = $fields[$key])
                        <div class="col-12 col-md-6">
                            <label for="{{ $key }}" class="form-label">{{ $field['label'] }}</label>
                            <div class="input-group">
                                <input type="number" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}"
                                    min="{{ $field['min'] }}" max="{{ $field['max'] }}" step="1" inputmode="numeric"
                                    data-default="{{ $defaults[$key] }}" class="form-control @error($key) is-invalid @enderror" required>
                                <span class="input-group-text">{{ $field['unit'] }}</span>
                                @error($key)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">{{ $field['help'] }} Bawaan: {{ number_format($defaults[$key], 0, ',', '.') }} {{ $field['unit'] }}.</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
            <button type="button" class="btn btn-outline-secondary" data-rental-reset>Kembalikan ke bawaan</button>
        </div>
    </form>
@endsection
