@extends('layouts.dashboard')

@section('title', 'Ubah Pengguna')

@section('content')
    @include('dashboard.users._form', [
        'action' => route('dashboard.users.update', $user),
        'method' => 'PUT',
    ])
@endsection
