@extends('layouts.app')

@section('title', 'Kendaraan')

@section('content')
    <h1 class="page-title mb-3">Pilih kendaraan</h1>

    <form method="GET" action="{{ route('vehicles.index') }}" class="filter-card mb-4">
        <div class="row g-2">
            <div class="col-12 col-lg-4">
                <label for="search" class="form-label small mb-1">Cari</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Merek atau model">
            </div>
            <div class="col-6 col-lg-2">
                <label for="type" class="form-label small mb-1">Kategori</label>
                <select id="type" name="type" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(($filters['type'] ?? null) === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="drive_type" class="form-label small mb-1">Tipe</label>
                <select id="drive_type" name="drive_type" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($driveTypes as $driveType)
                        <option value="{{ $driveType->value }}" @selected(($filters['drive_type'] ?? null) === $driveType->value)>{{ $driveType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="start_at" class="form-label small mb-1">Mulai ({{ config('rental.timezone_label') }})</label>
                <input type="datetime-local" id="start_at" name="start_at" value="{{ $filters['start_at'] ?? '' }}"
                    class="form-control @error('start_at') is-invalid @enderror">
            </div>
            <div class="col-6 col-lg-2">
                <label for="days" class="form-label small mb-1">Durasi</label>
                <select id="days" name="days" class="form-select @error('days') is-invalid @enderror">
                    <option value="">-</option>
                    @foreach ($dayChoices as $dayChoice)
                        <option value="{{ $dayChoice }}" @selected((int) ($filters['days'] ?? 0) === $dayChoice)>{{ $dayChoice }} hari</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($errors->any())
            <div class="text-danger small mt-2">{{ $errors->first() }}</div>
        @endif

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            <a href="{{ route('vehicles.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="row g-3 g-lg-4">
        @forelse ($vehicles as $vehicle)
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                <x-customer.vehicle-card :vehicle="$vehicle" :start-at="$filters['start_at'] ?? null" :days="$filters['days'] ?? null" />
            </div>
        @empty
            <div class="col-12">
                <p class="text-body-secondary py-5 text-center">Tidak ada kendaraan yang cocok dengan filter ini.</p>
            </div>
        @endforelse
    </div>

    @if ($vehicles->hasPages())
        <div class="mt-4">{{ $vehicles->links() }}</div>
    @endif
@endsection
