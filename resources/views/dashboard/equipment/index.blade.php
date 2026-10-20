@extends('layouts.dashboard')

@section('title', 'Perlengkapan')

@section('content')
    @include('dashboard.users._alerts')

    <div class="card-dash-user p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <h2 class="card-heading mb-0">Perlengkapan Kendaraan</h2>
            <a href="{{ route('dashboard.equipment.create') }}" class="btn btn-primary">Tambah Perlengkapan</a>
        </div>

        <form method="GET" action="{{ route('dashboard.equipment.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-xl-6">
                <label for="search" class="visually-hidden">Cari</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                    class="form-control" placeholder="Cari nama perlengkapan">
            </div>
            <div class="col-6 col-xl-3">
                <label for="category" class="visually-hidden">Kategori</label>
                <select id="category" name="category" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(($filters['category'] ?? null) === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-xl-3">
                <label for="status" class="visually-hidden">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">Terapkan</button>
                <a href="{{ route('dashboard.equipment.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Jumlah</th>
                        <th scope="col">Urutan</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td>{{ $item->category->label() }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->sort_order }}</td>
                            <td>
                                <span class="status-badge {{ $item->is_active ? 'status-lunas' : 'status-batal' }}">
                                    {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('dashboard.equipment.edit', $item) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteEquipmentModal"
                                    data-delete-url="{{ route('dashboard.equipment.destroy', $item) }}"
                                    data-delete-name="{{ $item->name }} ({{ $item->category->label() }})">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-5">Tidak ada perlengkapan yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <p class="small text-body-secondary mb-0">
                    Menampilkan {{ $items->firstItem() }}-{{ $items->lastItem() }} dari {{ $items->total() }} perlengkapan
                </p>
                {{ $items->links() }}
            </div>
        @endif
    </div>

    <x-dashboard.confirm-delete-modal id="deleteEquipmentModal" title="Hapus perlengkapan?" />
@endsection
