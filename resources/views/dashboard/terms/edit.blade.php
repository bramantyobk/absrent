@extends('layouts.dashboard')

@section('title', 'Syarat & Ketentuan')

@section('content')
    @include('dashboard.users._alerts')

    <div class="card-custom p-4" data-template-editor data-default-template="{{ $defaultTemplate }}" data-sample='@json($sample)'>
        <h2 class="card-heading">Syarat dan Ketentuan</h2>
        <p class="small text-body-secondary">
            Isi dokumen ini menjadi Surat Perjanjian Sewa Menyewa. Pelanggan membacanya sebelum pesanan dibuat dan hanya dapat menyetujuinya
            (tidak dapat mengubah). Baris berawalan <code># </code> menjadi judul, dan baris kosong memisahkan paragraf. Kosongkan untuk memakai template bawaan.
        </p>

        <form method="POST" action="{{ route('dashboard.terms.update') }}" novalidate>
            @csrf
            @method('PUT')

            <label for="terms_template" class="form-label">Template</label>
            <textarea id="terms_template" name="terms_template" rows="18" maxlength="{{ $maxLength }}" data-template-input
                class="form-control @error('terms_template') is-invalid @enderror">{{ old('terms_template', $template) }}</textarea>
            @error('terms_template')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <p class="small text-body-secondary mt-3 mb-1">Klik placeholder untuk menyisipkannya. Kode pesanan tidak tersedia karena pesanan belum dibuat saat dokumen dibaca:</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($placeholders as $placeholder => $description)
                    <button type="button" class="btn btn-sm btn-outline-primary" data-template-placeholder="{{ $placeholder }}" title="{{ $description }}">{{ $placeholder }}</button>
                @endforeach
            </div>

            <p class="small fw-semibold mb-1">Pratinjau dengan data contoh</p>
            <pre class="border rounded p-3 small bg-light" data-template-preview style="white-space: pre-wrap;"></pre>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-outline-secondary" data-template-reset>Kembalikan ke bawaan</button>
            </div>
        </form>
    </div>
@endsection
