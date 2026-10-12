@extends('layouts.dashboard')

@section('title', 'Ubah Kendaraan')

@section('content')
    @include('dashboard.vehicles._form', [
        'action' => route('dashboard.vehicles.update', $vehicle),
        'method' => 'PUT',
    ])
@endsection
