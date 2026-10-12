@extends('layouts.dashboard')

@section('title', 'Tambah Kendaraan')

@section('content')
    @include('dashboard.vehicles._form', [
        'action' => route('dashboard.vehicles.store'),
        'method' => 'POST',
    ])
@endsection
