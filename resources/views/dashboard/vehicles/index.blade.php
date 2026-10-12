@extends('layouts.dashboard')

@section('title', 'Kendaraan')

@section('content')
    @include('dashboard.users._alerts')

    <div class="card-dash-user p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <h2 class="card-heading mb-0">Daftar Kendaraan</h2>
            <a href="{{ route('dashboard.vehicles.create') }}" class="btn btn-primary">Tambah Kendaraan</a>
        </div>

        <form method="GET" action="{{ route('dashboard.vehicles.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-xl-6">
                <label for="search" class="visually-hidden">Cari</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                    class="form-control" placeholder="Cari merek, model, atau plat nomor">
            </div>
            <div class="col-6 col-xl-3">
                <label for="type" class="visually-hidden">Tipe</label>
                <select id="type" name="type" class="form-select">
                    <option value="">Semua tipe</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-xl-3">
                <label for="status" class="visually-hidden">Ketersediaan</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    <option value="available" @selected(($filters['status'] ?? null) === 'available')>Tersedia</option>
                    <option value="unavailable" @selected(($filters['status'] ?? null) === 'unavailable')>Tidak tersedia</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">Terapkan</button>
                <a href="{{ route('dashboard.vehicles.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table">
                <thead>
                    <tr>
                        <th scope="col">Kendaraan</th>
                        <th scope="col">Plat</th>
                        <th scope="col">Tarif / hari</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ $vehicle->photo_url }}" alt="" class="vehicle-thumb" loading="lazy">
                                    <div>
                                        <div class="fw-semibold">{{ $vehicle->brand }} {{ $vehicle->model }}</div>
                                        <div class="small text-body-secondary">
                                            {{ $vehicle->type->label() }} · {{ $vehicle->year }} · {{ $vehicle->color }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">{{ $vehicle->plate_number }}</td>
                            <td class="text-nowrap">Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}</td>
                            <td>
                                @if ($vehicle->is_available)
                                    <span class="status-badge status-lunas">Tersedia</span>
                                @else
                                    <span class="status-badge status-batal">Tidak tersedia</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('dashboard.vehicles.edit', $vehicle) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#deleteVehicleModal"
                                    data-delete-url="{{ route('dashboard.vehicles.destroy', $vehicle) }}"
                                    data-delete-name="{{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->plate_number }})">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-5">Tidak ada kendaraan yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($vehicles->hasPages())
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <p class="small text-body-secondary mb-0">
                    Menampilkan {{ $vehicles->firstItem() }}-{{ $vehicles->lastItem() }} dari {{ $vehicles->total() }} kendaraan
                </p>
                {{ $vehicles->links() }}
            </div>
        @endif
    </div>

    <x-dashboard.confirm-delete-modal id="deleteVehicleModal" title="Hapus kendaraan?" />
@endsection
