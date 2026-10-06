@extends('layouts.guest')

@section('title', 'Dashboard')

@section('content')
    <h1 class="h4 fw-bold">Dashboard</h1>
    <p class="text-body-secondary">
        Halo, {{ auth()->user()->name }} ({{ auth()->user()->role->label() }}). Halaman dashboard akan dibuat berikutnya.
    </p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary">Keluar</button>
    </form>
@endsection
