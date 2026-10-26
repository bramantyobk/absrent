@extends('layouts.dashboard')

@section('title', 'Pesanan Manual')

@php
    $hasPeriod = filled($period['start_at'] ?? null) && filled($period['days'] ?? null);
    $customerMode = old('customer_id') ? 'existing' : (old('new_customer') ? 'new' : 'existing');
@endphp

@section('content')
    @include('dashboard.users._alerts')

    <a href="{{ route('dashboard.orders.index') }}" class="small fw-semibold">&larr; Daftar pesanan</a>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
        <h2 class="card-heading mb-0">Buat Pesanan Manual</h2>
    </div>

    @if ($isOutsideOfficeHours)
        <div class="alert alert-warning" role="alert">
            Saat ini di luar jam layanan kantor. Pesanan tetap dapat dibuat sesuai kebijakan Anda.
        </div>
    @endif

    <div class="card-custom p-4 mb-4">
        <h3 class="card-heading fs-6">1. Periode sewa</h3>
        <p class="small text-body-secondary">Satu pesanan satu periode, maksimal {{ $maxVehicles }} kendaraan. Aturan bentrok, jeda, dan durasi sama dengan pesanan online.</p>

        <form method="GET" action="{{ route('dashboard.orders.manual.create') }}" class="row g-2 align-items-start">
            <div class="col-12 col-md-6">
                <x-datetime-field id="start_at" label="Waktu mulai" :value="$period['start_at'] ?? $defaultStart" :timezone="$timezoneLabel"
                    :min-date="now()->format('Y-m-d')" show-return required />
            </div>
            <div class="col-6 col-md-3">
                <label for="days" class="form-label">Durasi</label>
                <select id="days" name="days" class="form-select">
                    @foreach (range(1, config('rental.max_rental_days')) as $day)
                        <option value="{{ $day }}" @selected((int) ($period['days'] ?? 1) === $day)>{{ $day }} hari</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <span class="form-label d-none d-md-block invisible" aria-hidden="true">&nbsp;</span>
                <button type="submit" class="btn btn-outline-primary w-100">Cari unit kosong</button>
            </div>
        </form>
    </div>

    @if ($hasPeriod)
        <form method="POST" action="{{ route('dashboard.orders.manual.store') }}" enctype="multipart/form-data" novalidate data-manual-order
            data-lookup-url="{{ route('dashboard.customers.lookup') }}" data-max-vehicles="{{ $maxVehicles }}">
            @csrf
            <input type="hidden" name="start_at" value="{{ $period['start_at'] }}">
            <input type="hidden" name="days" value="{{ $period['days'] }}">

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card-custom p-4 mb-4">
                <h3 class="card-heading fs-6">2. Kendaraan</h3>
                <p class="small text-body-secondary">Unit yang kosong pada {{ \Illuminate\Support\Carbon::parse($period['start_at'])->format('d/m/Y H:i') }} {{ $timezoneLabel }} selama {{ $period['days'] }} hari.</p>

                @forelse ($vehicles as $vehicle)
                    @php($index = $loop->index)
                    <div class="form-check border rounded p-3 ps-5 mb-2">
                        <input type="checkbox" class="form-check-input" id="vehicle_{{ $vehicle->id }}" name="items[{{ $index }}][vehicle_id]" value="{{ $vehicle->id }}"
                            data-vehicle-choice @checked(collect(old('items'))->pluck('vehicle_id')->contains($vehicle->id))>
                        <label for="vehicle_{{ $vehicle->id }}" class="form-check-label d-flex align-items-center gap-3">
                            <img src="{{ $vehicle->photo_url }}" alt="" class="vehicle-thumb" loading="lazy">
                            <span>
                                <span class="fw-semibold">{{ $vehicle->brand }} {{ $vehicle->model }}</span>
                                <span class="text-body-secondary small">({{ $vehicle->plate_number }}) &middot; {{ $vehicle->type->label() }}</span><br>
                                <span class="small">Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}/hari &times; {{ $period['days'] }} = <strong>Rp{{ number_format($vehicle->price_per_day * $period['days'], 0, ',', '.') }}</strong></span>
                            </span>
                        </label>
                    </div>
                @empty
                    <div class="alert alert-warning mb-0" role="alert">Tidak ada unit yang kosong pada periode ini. Ubah periode lalu cari lagi.</div>
                @endforelse
            </div>

            @if ($vehicles->isNotEmpty())
                <div class="card-custom p-4 mb-4">
                    <h3 class="card-heading fs-6">3. Pelanggan</h3>

                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input type="radio" class="form-check-input" name="customer_mode" id="mode_existing" value="existing" data-customer-mode @checked($customerMode === 'existing')>
                            <label for="mode_existing" class="form-check-label">Pelanggan yang ada</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="radio" class="form-check-input" name="customer_mode" id="mode_new" value="new" data-customer-mode @checked($customerMode === 'new')>
                            <label for="mode_new" class="form-check-label">Pelanggan baru</label>
                        </div>
                    </div>

                    <div data-customer-section="existing">
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-md-5">
                                <label for="lookup_phone" class="form-label">Nomor WhatsApp</label>
                                <input type="tel" id="lookup_phone" class="form-control" placeholder="081234567890" inputmode="tel" data-lookup-phone>
                            </div>
                            <div class="col-12 col-md-5">
                                <label for="lookup_query" class="form-label">Email atau nama</label>
                                <input type="search" id="lookup_query" class="form-control" data-lookup-query>
                            </div>
                            <div class="col-12 col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-primary w-100" data-lookup-button>Cari</button>
                            </div>
                        </div>
                        <div class="small text-danger mb-2 d-none" data-lookup-error></div>
                        <div class="d-flex flex-column gap-2 mb-2" data-lookup-results></div>

                        <input type="hidden" name="customer_id" value="{{ old('customer_id') }}" data-customer-id>
                        <input type="hidden" name="customer_label" value="{{ old('customer_label') }}" data-customer-label>
                        <div class="alert alert-info py-2 mb-0 {{ old('customer_id') ? '' : 'd-none' }}" data-customer-selected>
                            Pelanggan terpilih: <strong data-customer-selected-name>{{ old('customer_label') }}</strong>
                        </div>
                    </div>

                    <div class="d-none" data-customer-section="new">
                        <p class="small text-body-secondary">
                            Role selalu pelanggan dan password diacak (pelanggan mengatur lewat lupa password atau admin mereset). Bila nomor sama dengan
                            pelanggan lain yang namanya berbeda, pelanggan lama ditandai "Nomor perlu ditinjau".
                        </p>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="new_name" class="form-label">Nama</label>
                                <input type="text" id="new_name" name="new_customer[name]" value="{{ old('new_customer.name') }}" maxlength="255" class="form-control" data-new-field>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="new_phone" class="form-label">Nomor WhatsApp</label>
                                <input type="tel" id="new_phone" name="new_customer[phone]" value="{{ old('new_customer.phone') }}" inputmode="tel" class="form-control" data-new-field>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="new_email" class="form-label">Email</label>
                                <input type="email" id="new_email" name="new_customer[email]" value="{{ old('new_customer.email') }}" maxlength="255" class="form-control" data-new-field>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-custom p-4 mb-4">
                    <h3 class="card-heading fs-6">4. Pembayaran di tempat</h3>
                    <p class="small text-body-secondary">Pembayaran dicatat langsung Lunas dan pesanan berstatus Booking. Bukti dan rekening opsional.</p>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="bank_account_id" class="form-label">Rekening tujuan (bila transfer)</label>
                            <select id="bank_account_id" name="bank_account_id" class="form-select">
                                <option value="">Tunai / tanpa rekening</option>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((int) old('bank_account_id') === $account->id)>{{ $account->bank_name }} {{ $account->account_number }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="payment_note" class="form-label">Catatan pembayaran</label>
                            <input type="text" id="payment_note" name="payment_note" value="{{ old('payment_note') }}" maxlength="500" class="form-control" placeholder="Mis. tunai di kantor">
                        </div>
                        <div class="col-12">
                            <label for="proof" class="form-label">Bukti pembayaran (opsional)</label>
                            <input type="file" id="proof" name="proof" accept="image/jpeg,image/png" class="form-control @error('proof') is-invalid @enderror">
                            <div class="form-text">JPG atau PNG, maksimal 2 MB. Disimpan privat.</div>
                            @error('proof')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Buat pesanan (langsung Lunas)</button>
            @endif
        </form>
    @endif
@endsection
