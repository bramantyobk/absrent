@extends('layouts.dashboard')

@section('title', 'Tambah Perlengkapan')

@section('content')
    @include('dashboard.equipment._form', [
        'action' => route('dashboard.equipment.store'),
        'method' => 'POST',
    ])
@endsection
