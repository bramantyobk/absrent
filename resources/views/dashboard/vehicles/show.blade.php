@extends('layouts.dashboard')

@section('title', 'Detail Kendaraan')

@php
    $dateFormat = 'd/m/Y H:i';
    $inputFormat = 'Y-m-d\TH:i';
    $scheduleErrors = $errors->getBag('schedule');
    $odometerErrors = $errors->getBag('odometer');
    $lastOdometer = $vehicle->odometer_km;
    $scheduleStoreUrl = route('dashboard.vehicles.schedules.store', $vehicle);
    $scheduleAction = old('schedule_form_action', $scheduleStoreUrl);
    $scheduleMethod = old('schedule_form_method', 'POST');
    $rentalLimitErrors = $errors->getBag('rental_limit');
@endphp

@section('content')
    <div data-vehicle-detail @if ($autoOpenModal) data-auto-open="{{ $autoOpenModal }}" @endif>
        @include('dashboard.users._alerts')

        @if ($scheduleErrors->any() || $odometerErrors->any() || $rentalLimitErrors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach (array_merge($scheduleErrors->all(), $odometerErrors->all(), $rentalLimitErrors->all()) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="{{ route('dashboard.vehicles.index') }}" class="btn btn-outline-secondary">Kembali</a>
            <a href="{{ route('dashboard.vehicles.edit', $vehicle) }}" class="btn btn-outline-primary">Ubah kendaraan</a>
        </div>

        {{-- A. Informasi kendaraan --}}
        <div class="card-dash-user p-4 mb-4">
            <div class="d-flex flex-wrap gap-4">
                <img src="{{ $vehicle->photo_url }}" alt="Foto {{ $vehicle->brand }} {{ $vehicle->model }}" class="vehicle-photo-preview">
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h2 class="card-heading mb-0">{{ $vehicle->brand }} {{ $vehicle->model }}</h2>
                        <span class="status-badge {{ $status->badgeClass() }}">{{ $status->label() }}</span>
                    </div>
                    <div class="text-body-secondary mb-3">{{ $vehicle->plate_number }}</div>

                    <dl class="row mb-0 vehicle-detail-list">
                        <dt class="col-6 col-md-3">Kategori</dt>
                        <dd class="col-6 col-md-3">{{ $vehicle->type->label() }}</dd>
                        <dt class="col-6 col-md-3">Tipe</dt>
                        <dd class="col-6 col-md-3">{{ $vehicle->drive_type?->label() ?? '-' }}</dd>
                        <dt class="col-6 col-md-3">Tahun</dt>
                        <dd class="col-6 col-md-3">{{ $vehicle->year }}</dd>
                        <dt class="col-6 col-md-3">Warna</dt>
                        <dd class="col-6 col-md-3">{{ $vehicle->color }}</dd>
                        <dt class="col-6 col-md-3">Kapasitas energi</dt>
                        <dd class="col-6 col-md-3">{{ $vehicle->energyCapacityLabel() ?? '-' }}</dd>
                        <dt class="col-6 col-md-3">Tarif / hari</dt>
                        <dd class="col-6 col-md-3">Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}</dd>
                        <dt class="col-6 col-md-3">Tarif / jam (info)</dt>
                        <dd class="col-6 col-md-3">{{ $vehicle->price_per_hour !== null ? 'Rp'.number_format($vehicle->price_per_hour, 0, ',', '.') : '-' }}</dd>
                        <dt class="col-6 col-md-3">Katalog</dt>
                        <dd class="col-6 col-md-3">{{ $vehicle->is_available ? 'Tampil' : 'Tidak tampil' }}</dd>
                        <dt class="col-6 col-md-3">Odometer terakhir</dt>
                        <dd class="col-6 col-md-9 d-flex flex-wrap align-items-center gap-2">
                            {{ $lastOdometer !== null ? number_format($lastOdometer, 0, ',', '.').' km' : '-' }}
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#odometerModal">Perbarui odometer</button>
                        </dd>
                        <dt class="col-6 col-md-3">Hari sewa maksimal</dt>
                        <dd class="col-6 col-md-9 d-flex flex-wrap align-items-center gap-2">
                            {{ $vehicle->effectiveMaxRentalDays() }} hari
                            <span class="small text-body-secondary">({{ $vehicle->max_rental_days ? 'khusus unit ini' : 'bawaan' }})</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rentalLimitModal">Atur</button>
                        </dd>
                        <dt class="col-12 col-md-3">Deskripsi</dt>
                        <dd class="col-12 col-md-9">{{ $vehicle->description ?: '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- B. Riwayat jadwal --}}
        <div class="card-dash-user p-4 mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <h2 class="card-heading mb-0">Riwayat jadwal</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal"
                    data-schedule-create data-schedule-url="{{ $scheduleStoreUrl }}">Jadwalkan servis</button>
            </div>

            <h3 class="fs-6 fw-semibold">Jendela kosong</h3>
            <p class="small text-body-secondary">
                Waktu bebas {{ $freeWindowHorizonDays }} hari ke depan (minimal {{ config('rental.min_free_window_hours') }} jam),
                sudah termasuk jeda {{ config('rental.return_buffer_hours') }} jam setelah pesanan.
            </p>
            @forelse ($freeWindows as $window)
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded p-2 mb-2">
                    <span>{{ $window['start_at']->format($dateFormat) }} - {{ $window['end_at']->format($dateFormat) }}</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal"
                        data-schedule-create data-schedule-url="{{ $scheduleStoreUrl }}"
                        data-window-start="{{ $window['start_at']->format($inputFormat) }}"
                        data-window-end="{{ $window['end_at']->format($inputFormat) }}">Jadwalkan servis di sini</button>
                </div>
            @empty
                <p class="text-body-secondary">Tidak ada jendela kosong.</p>
            @endforelse

            <h3 class="fs-6 fw-semibold mt-4">Pesanan dan servis</h3>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 users-table">
                    <thead>
                        <tr>
                            <th scope="col">Periode</th>
                            <th scope="col">Pesanan / jenis</th>
                            <th scope="col">Status</th>
                            <th scope="col">Odometer</th>
                            <th scope="col">Bebas pada</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($timeline as $row)
                            <tr>
                                <td class="text-nowrap">{{ $row['start_at']->format($dateFormat) }}<br>{{ $row['end_at']->format($dateFormat) }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $row['title'] }}</div>
                                    @if ($row['subtitle'])
                                        <div class="small text-body-secondary">{{ $row['subtitle'] }}</div>
                                    @endif
                                    @if ($row['note'])
                                        <div class="small text-body-secondary">{{ $row['note'] }}</div>
                                    @endif
                                </td>
                                <td><span class="status-badge {{ $row['status_class'] }}">{{ $row['status_label'] }}</span></td>
                                <td class="text-nowrap">
                                    @if ($row['kind'] === 'order')
                                        {{ $row['odometer_start'] !== null ? number_format($row['odometer_start'], 0, ',', '.') : '-' }}
                                        &rarr;
                                        {{ $row['odometer_end'] !== null ? number_format($row['odometer_end'], 0, ',', '.') : '-' }}
                                        @if ($row['distance_km'] !== null)
                                            <div class="small text-body-secondary">{{ number_format($row['distance_km'], 0, ',', '.') }} km</div>
                                        @endif
                                    @else
                                        {{ $row['odometer_start'] !== null ? number_format($row['odometer_start'], 0, ',', '.').' km' : '-' }}
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ $row['free_at'] ? $row['free_at']->format($dateFormat) : 'Menunggu diselesaikan' }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($row['schedule'] && ! $row['schedule']->hasEnded())
                                        @php($schedule = $row['schedule'])
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal"
                                            data-schedule-edit
                                            data-schedule-url="{{ route('dashboard.vehicles.schedules.update', [$vehicle, $schedule]) }}"
                                            data-schedule-running="{{ $schedule->hasStarted() ? '1' : '0' }}"
                                            data-schedule-type="{{ $schedule->type->value }}"
                                            data-schedule-start="{{ $schedule->start_at->format($inputFormat) }}"
                                            data-schedule-end="{{ $schedule->end_at->format($inputFormat) }}"
                                            data-schedule-note="{{ $schedule->note }}"
                                            data-schedule-odometer="{{ $schedule->odometer_km }}">Ubah</button>
                                        @unless ($schedule->hasStarted())
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelScheduleModal"
                                                data-delete-url="{{ route('dashboard.vehicles.schedules.destroy', [$vehicle, $schedule]) }}"
                                                data-delete-name="jadwal {{ $schedule->type->label() }} {{ $schedule->start_at->format($dateFormat) }}">Batalkan</button>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-body-secondary py-5">Belum ada pesanan atau jadwal servis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- C. Perlengkapan: daftar baca saja dari master perlengkapan aktif sesuai kategori unit --}}
        <div class="card-dash-user p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <h2 class="card-heading mb-0">Perlengkapan ({{ $vehicle->type->label() }})</h2>
                <a href="{{ route('dashboard.equipment.index', ['category' => $vehicle->type->value]) }}" class="btn btn-sm btn-outline-secondary">Kelola perlengkapan</a>
            </div>
            @forelse ($equipment as $item)
                @if ($loop->first)
                    <ul class="list-group list-group-flush">
                @endif
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span>{{ $item->name }}</span>
                    <span class="text-body-secondary">{{ $item->quantity }}</span>
                </li>
                @if ($loop->last)
                    </ul>
                @endif
            @empty
                <p class="text-body-secondary mb-0">Belum ada perlengkapan aktif untuk kategori {{ $vehicle->type->label() }}.</p>
            @endforelse
        </div>
    </div>

    {{-- Modal jadwal servis (tambah dan ubah) --}}
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ $scheduleAction }}" class="modal-content" data-schedule-form data-store-url="{{ $scheduleStoreUrl }}" novalidate>
                @csrf
                <input type="hidden" name="_method" value="{{ $scheduleMethod === 'PUT' ? 'PUT' : 'POST' }}" data-schedule-method>
                <input type="hidden" name="schedule_form_action" value="{{ $scheduleAction }}" data-schedule-action-field>
                <input type="hidden" name="schedule_form_method" value="{{ $scheduleMethod }}" data-schedule-method-field>

                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="scheduleModalLabel" data-schedule-title>Jadwalkan servis</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="schedule_type" class="form-label">Jenis</label>
                        <select id="schedule_type" name="type" class="form-select" required>
                            @foreach ($scheduleTypes as $scheduleType)
                                <option value="{{ $scheduleType->value }}" @selected(old('type') === $scheduleType->value)>{{ $scheduleType->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="schedule_start_at" class="form-label">Mulai</label>
                            <input type="datetime-local" id="schedule_start_at" name="start_at" value="{{ old('start_at') }}" class="form-control" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="schedule_end_at" class="form-label">Selesai</label>
                            <input type="datetime-local" id="schedule_end_at" name="end_at" value="{{ old('end_at') }}" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-text mb-3 d-none" data-schedule-running-hint>
                        Servis ini sedang berjalan: hanya waktu selesai, catatan, dan odometer yang dapat diubah.
                    </div>
                    <div class="mb-3">
                        <label for="schedule_note" class="form-label">Catatan</label>
                        <input type="text" id="schedule_note" name="note" value="{{ old('note') }}" maxlength="255" class="form-control">
                    </div>
                    <div>
                        <label for="schedule_odometer" class="form-label">Odometer saat servis (km)</label>
                        <input type="number" id="schedule_odometer" name="odometer_km" min="0" value="{{ old('odometer_km') }}" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal perbarui odometer --}}
    <div class="modal fade" id="odometerModal" tabindex="-1" aria-labelledby="odometerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('dashboard.vehicles.odometer.store', $vehicle) }}" class="modal-content" novalidate>
                @csrf

                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="odometerModalLabel">Perbarui odometer</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="odometer_km" class="form-label">Odometer baru (km)</label>
                        <input type="number" id="odometer_km" name="odometer_km" value="{{ old('odometer_km', $lastOdometer) }}"
                            min="{{ auth()->user()->isAdmin() ? 0 : ($lastOdometer ?? 0) }}" class="form-control" required>
                        <div class="form-text">
                            Odometer terakhir: {{ $lastOdometer !== null ? number_format($lastOdometer, 0, ',', '.').' km' : '-' }}.
                            Koreksi ke nilai yang lebih kecil hanya oleh admin dan wajib disertai catatan.
                        </div>
                    </div>
                    <div>
                        <label for="odometer_note" class="form-label">Catatan</label>
                        <textarea id="odometer_note" name="note" rows="2" maxlength="500" class="form-control">{{ old('note') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>


    {{-- Modal hari sewa maksimal --}}
    <div class="modal fade" id="rentalLimitModal" tabindex="-1" aria-labelledby="rentalLimitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('dashboard.vehicles.rental-limit.update', $vehicle) }}" class="modal-content" novalidate>
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="rentalLimitModalLabel">Hari sewa maksimal</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label for="max_rental_days" class="form-label">Hari sewa maksimal (unit ini)</label>
                    <input type="number" id="max_rental_days" name="max_rental_days" min="1" max="{{ $rentalLimitMaxDays }}"
                        value="{{ old('max_rental_days', $vehicle->max_rental_days) }}" placeholder="{{ config('rental.max_rental_days') }}" class="form-control">
                    <div class="form-text">
                        Kosongkan untuk memakai batas bawaan ({{ config('rental.max_rental_days') }} hari). Berlaku untuk pesanan berikutnya.
                        Durasi minimal tetap {{ config('rental.min_rental_hours') }} jam.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <x-dashboard.confirm-delete-modal id="cancelScheduleModal" title="Batalkan jadwal servis?" verb="membatalkan" button="Batalkan jadwal" />
@endsection
