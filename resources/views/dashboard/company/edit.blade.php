@extends('layouts.dashboard')

@section('title', 'Data Perusahaan')

@section('content')
    @include('dashboard.users._alerts')

    <form method="POST" action="{{ route('dashboard.company.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="card-custom p-4 mb-4">
            <h2 class="card-heading">Info Perusahaan</h2>
            <p class="small text-body-secondary">Tampil di footer, halaman pesanan, halaman pembayaran, dan tombol WhatsApp support.</p>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Nama perusahaan</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $company->name) }}" maxlength="150"
                        class="form-control @error('name') is-invalid @enderror" required autofocus>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="support_whatsapp" class="form-label">Nomor WhatsApp support</label>
                    <input type="tel" id="support_whatsapp" name="support_whatsapp" value="{{ old('support_whatsapp', $company->support_whatsapp) }}"
                        placeholder="081234567890" inputmode="tel" class="form-control @error('support_whatsapp') is-invalid @enderror" required>
                    <div class="form-text">Disimpan dalam format 62...</div>
                    @error('support_whatsapp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="address" class="form-label">Alamat</label>
                    <textarea id="address" name="address" rows="3" maxlength="1000"
                        class="form-control @error('address') is-invalid @enderror" required>{{ old('address', $company->address) }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card-custom p-4">
            <h2 class="card-heading">Jam Layanan Kantor</h2>
            <p class="small text-body-secondary">
                Jam layanan booking langsung di kantor, hanya sebagai informasi. Pemesanan online, ambil, dan kembali dilayani 24 jam.
            </p>

            @foreach ($hours as $index => $day)
                @php
                    $isOpen = (bool) old("operating_hours.{$index}.is_open", $day['is_open']);
                    $openAt = old("operating_hours.{$index}.open_at", $day['open_at']);
                    $closeAt = old("operating_hours.{$index}.close_at", $day['close_at']);
                @endphp

                <div class="row g-2 align-items-center py-2 border-top" data-hours-row>
                    <input type="hidden" name="operating_hours[{{ $index }}][day]" value="{{ $day['day'] }}">
                    <input type="hidden" name="operating_hours[{{ $index }}][is_open]" value="0">

                    <div class="col-12 col-md-4 d-flex align-items-center justify-content-between">
                        <span class="fw-semibold">{{ $day['label'] }}</span>
                        <div class="form-check form-switch mb-0">
                            <input type="checkbox" role="switch" id="is_open_{{ $index }}" name="operating_hours[{{ $index }}][is_open]" value="1"
                                class="form-check-input" data-hours-toggle @checked($isOpen)>
                            <label for="is_open_{{ $index }}" class="form-check-label">Buka</label>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <label for="open_at_{{ $index }}" class="visually-hidden">Jam buka {{ $day['label'] }}</label>
                        <input type="time" id="open_at_{{ $index }}" name="operating_hours[{{ $index }}][open_at]" value="{{ $openAt }}"
                            class="form-control @error("operating_hours.{$index}.open_at") is-invalid @enderror" data-hours-time @disabled(! $isOpen)>
                        @error("operating_hours.{$index}.open_at")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-6 col-md-4">
                        <label for="close_at_{{ $index }}" class="visually-hidden">Jam tutup {{ $day['label'] }}</label>
                        <input type="time" id="close_at_{{ $index }}" name="operating_hours[{{ $index }}][close_at]" value="{{ $closeAt }}"
                            class="form-control @error("operating_hours.{$index}.close_at") is-invalid @enderror" data-hours-time @disabled(! $isOpen)>
                        @error("operating_hours.{$index}.close_at")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            @endforeach

            @error('operating_hours')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
@endsection
