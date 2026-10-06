@extends('layouts.dashboard')

@section('title', 'Pengguna')

@section('content')
    @php($canManage = auth()->user()->isAdmin())

    @include('dashboard.users._alerts')

    @if ($canManage)
        <div id="dutyAlert" class="alert alert-danger d-none" role="alert"></div>
    @endif

    <div class="card-dash-user p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <h2 class="card-heading mb-0">Daftar Pengguna</h2>
            @if ($canManage)
                <a href="{{ route('dashboard.users.create') }}" class="btn btn-primary">Tambah Pengguna</a>
            @endif
        </div>

        <form method="GET" action="{{ route('dashboard.users.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-xl-6">
                <label for="search" class="visually-hidden">Cari</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                    class="form-control" placeholder="Cari nama, email, atau nomor WhatsApp">
            </div>
            <div class="col-6 col-xl-3">
                <label for="role" class="visually-hidden">Role</label>
                <select id="role" name="role" class="form-select">
                    <option value="">Semua role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(($filters['role'] ?? null) === $role->value)>{{ $role->label() }}</option>
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
                <a href="{{ route('dashboard.users.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table">
                <thead>
                    <tr>
                        <th scope="col">Pengguna</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Bertugas</th>
                        @if ($canManage)
                            <th scope="col" class="text-end">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php($isSelf = $user->is(auth()->user()))

                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    {{ $user->name }}
                                    @if ($isSelf)
                                        <span class="badge text-bg-light border ms-1">Anda</span>
                                    @endif
                                </div>
                                <div class="small text-body-secondary">{{ $user->email }}</div>
                                @if ($user->phone)
                                    <a href="https://wa.me/{{ $user->phone }}" target="_blank" rel="noopener" class="small">{{ $user->phone }}</a>
                                @endif
                            </td>
                            <td><span class="badge {{ $user->role->badgeClass() }}">{{ $user->role->label() }}</span></td>
                            <td>
                                @if ($user->is_active)
                                    <span class="status-badge status-lunas">Aktif</span>
                                @else
                                    <span class="status-badge status-batal">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->role !== \App\UserRole::Operator)
                                    <span class="text-body-tertiary">-</span>
                                @elseif ($canManage)
                                    <div class="form-check form-switch mb-0">
                                        <input type="checkbox" role="switch" class="form-check-input"
                                            id="duty-{{ $user->id }}"
                                            data-duty-toggle
                                            data-url="{{ route('dashboard.users.duty-status', $user) }}"
                                            @checked($user->is_on_duty)
                                            @disabled(! $user->is_active)>
                                        <label class="form-check-label small" for="duty-{{ $user->id }}" data-duty-label>
                                            {{ $user->is_on_duty ? 'Bertugas' : 'Libur' }}
                                        </label>
                                    </div>
                                @elseif ($user->is_on_duty)
                                    <span class="status-badge status-lunas">Bertugas</span>
                                @else
                                    <span class="status-badge status-pending">Libur</span>
                                @endif
                            </td>
                            @if ($canManage)
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('dashboard.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Ubah</a>

                                    @if ($isSelf)
                                        <span class="d-inline-block" tabindex="0" title="Anda tidak dapat menghapus akun sendiri">
                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled>Hapus</button>
                                        </span>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                            data-delete-url="{{ route('dashboard.users.destroy', $user) }}"
                                            data-delete-name="{{ $user->name }}">Hapus</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 5 : 4 }}" class="text-center text-body-secondary py-5">Tidak ada pengguna yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <p class="small text-body-secondary mb-0">
                    Menampilkan {{ $users->firstItem() }}-{{ $users->lastItem() }} dari {{ $users->total() }} pengguna
                </p>
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @if ($canManage)
        <x-dashboard.confirm-delete-modal id="deleteUserModal" title="Hapus pengguna?" />
    @endif
@endsection
