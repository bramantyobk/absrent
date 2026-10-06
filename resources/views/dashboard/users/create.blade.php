@extends('layouts.dashboard')

@section('title', 'Tambah Pengguna')

@section('content')
    @include('dashboard.users._form', [
        'action' => route('dashboard.users.store'),
        'method' => 'POST',
    ])
@endsection
