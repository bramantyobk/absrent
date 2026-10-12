@extends('layouts.dashboard')

@section('title', 'Ubah Perlengkapan')

@section('content')
    @include('dashboard.equipment._form', [
        'action' => route('dashboard.equipment.update', $equipment),
        'method' => 'PUT',
    ])
@endsection
