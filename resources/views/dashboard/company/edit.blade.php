@extends('layouts.dashboard')

@section('title', 'Data Perusahaan')

@php
    $bankErrors = $errors->getBag('bank_account');
    $bankStoreUrl = route('dashboard.company.bank-accounts.store');
    $bankAction = old('bank_form_action', $bankStoreUrl);
    $bankMethod = old('bank_form_method', 'POST');
@endphp

@section('content')
    @include('dashboard.users._alerts')

    <form method="POST" action="{{ route('dashboard.company.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="card-custom p-4 mb-4">
            <h2 class="card-heading">Info Perusahaan</h2>
            <p class="small text-body-secondary">Tampil di footer, halaman pesanan, halaman pembayaran, dan tombol WhatsApp support.</p>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Nama perusahaan</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $company->name) }}" maxlength="150"
                        class="form-control @error('name') is-invalid @enderror" required autofocus>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="support_whatsapp" class="form-label">Nomor WhatsApp support</label>
                    <input type="tel" id="support_whatsapp" name="support_whatsapp" value="{{ old('support_whatsapp', $company->support_whatsapp) }}"
                        placeholder="081234567890" inputmode="tel" class="form-control @error('support_whatsapp') is-invalid @enderror" required>
                    <div class="form-text">Disimpan dalam format 62...</div>
                    @error('support_whatsapp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="address" class="form-label">Alamat</label>
                    <textarea id="address" name="address" rows="3" maxlength="1000"
                        class="form-control @error('address') is-invalid @enderror" required>{{ old('address', $company->address) }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card-custom p-4">
            <h2 class="card-heading">Jam Layanan Kantor</h2>
            <p class="small text-body-secondary">
                Jam layanan booking langsung di kantor, hanya sebagai informasi. Pemesanan online, ambil, dan kembali dilayani 24 jam.
            </p>

            @foreach ($hours as $index => $day)
                @php
                    $isOpen = (bool) old("operating_hours.{$index}.is_open", $day['is_open']);
                    $openAt = old("operating_hours.{$index}.open_at", $day['open_at']);
                    $closeAt = old("operating_hours.{$index}.close_at", $day['close_at']);
                @endphp

                <div class="row g-2 align-items-center py-2 border-top" data-hours-row>
                    <input type="hidden" name="operating_hours[{{ $index }}][day]" value="{{ $day['day'] }}">
                    <input type="hidden" name="operating_hours[{{ $index }}][is_open]" value="0">

                    <div class="col-12 col-md-4 d-flex align-items-center justify-content-between">
                        <span class="fw-semibold">{{ $day['label'] }}</span>
                        <div class="form-check form-switch mb-0">
                            <input type="checkbox" role="switch" id="is_open_{{ $index }}" name="operating_hours[{{ $index }}][is_open]" value="1"
                                class="form-check-input" data-hours-toggle @checked($isOpen)>
                            <label for="is_open_{{ $index }}" class="form-check-label">Buka</label>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <label for="open_at_{{ $index }}" class="visually-hidden">Jam buka {{ $day['label'] }}</label>
                        <input type="time" id="open_at_{{ $index }}" name="operating_hours[{{ $index }}][open_at]" value="{{ $openAt }}"
                            class="form-control @error("operating_hours.{$index}.open_at") is-invalid @enderror" data-hours-time @disabled(! $isOpen)>
                        @error("operating_hours.{$index}.open_at")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-6 col-md-4">
                        <label for="close_at_{{ $index }}" class="visually-hidden">Jam tutup {{ $day['label'] }}</label>
                        <input type="time" id="close_at_{{ $index }}" name="operating_hours[{{ $index }}][close_at]" value="{{ $closeAt }}"
                            class="form-control @error("operating_hours.{$index}.close_at") is-invalid @enderror" data-hours-time @disabled(! $isOpen)>
                        @error("operating_hours.{$index}.close_at")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            @endforeach

            @error('operating_hours')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>

    {{-- Rekening bank: berada di luar form di atas karena memakai form modal sendiri --}}
    <div class="card-custom p-4 mt-4" data-bank-accounts @if ($bankErrors->any()) data-auto-open="bankAccountModal" @endif>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
            <h2 class="card-heading mb-0">Rekening Bank</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bankAccountModal"
                data-bank-create data-bank-url="{{ $bankStoreUrl }}">Tambah rekening</button>
        </div>
        <p class="small text-body-secondary">
            Rekening tujuan transfer yang tampil di halaman pembayaran, sesuai urutan di bawah. Rekening tidak dapat dihapus, hanya dinonaktifkan,
            agar data transaksi tetap valid.
        </p>

        @if ($bankErrors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($bankErrors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($bankAccounts->where('is_active', true)->isEmpty())
            <div class="alert alert-warning" role="alert">
                Belum ada rekening aktif. Halaman pembayaran tidak akan menampilkan rekening tujuan.
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table">
                <thead>
                    <tr>
                        <th scope="col">Bank</th>
                        <th scope="col">Nomor rekening</th>
                        <th scope="col">Atas nama</th>
                        <th scope="col">Urutan</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bankAccounts as $account)
                        <tr>
                            <td class="fw-semibold">{{ $account->bank_name }}</td>
                            <td class="text-nowrap">{{ $account->account_number }}</td>
                            <td>{{ $account->account_holder }}</td>
                            <td>{{ $account->sort_order }}</td>
                            <td>
                                <span class="status-badge {{ $account->is_active ? 'status-lunas' : 'status-batal' }}">
                                    {{ $account->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bankAccountModal"
                                    data-bank-edit
                                    data-bank-url="{{ route('dashboard.company.bank-accounts.update', $account) }}"
                                    data-bank-name="{{ $account->bank_name }}"
                                    data-bank-number="{{ $account->account_number }}"
                                    data-bank-holder="{{ $account->account_holder }}"
                                    data-bank-order="{{ $account->sort_order }}"
                                    data-bank-active="{{ $account->is_active ? '1' : '0' }}">Ubah</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-5">Belum ada rekening bank.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-custom p-4 mt-4" data-template-editor data-default-template="{{ $defaultWhatsAppTemplate }}" data-sample='@json($whatsAppSample)'>
        <h2 class="card-heading">Template Pesan WhatsApp</h2>
        <p class="small text-body-secondary">
            Isi pesan konfirmasi pembayaran yang dikirim pelanggan ke operator. Kosongkan untuk memakai template bawaan.
        </p>

        <form method="POST" action="{{ route('dashboard.company.whatsapp-template.update') }}" novalidate>
            @csrf
            @method('PUT')

            <label for="whatsapp_confirmation_template" class="form-label">Template</label>
            <textarea id="whatsapp_confirmation_template" name="whatsapp_confirmation_template" rows="6" maxlength="1000" data-template-input
                class="form-control @error('whatsapp_confirmation_template') is-invalid @enderror">{{ old('whatsapp_confirmation_template', $whatsAppTemplate) }}</textarea>
            @error('whatsapp_confirmation_template')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <p class="small text-body-secondary mt-3 mb-1">Klik placeholder untuk menyisipkannya ({{ '{order_code}' }} wajib ada):</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($whatsAppPlaceholders as $placeholder => $description)
                    <button type="button" class="btn btn-sm btn-outline-primary" data-template-placeholder="{{ $placeholder }}" title="{{ $description }}">{{ $placeholder }}</button>
                @endforeach
            </div>

            <p class="small fw-semibold mb-1">Pratinjau dengan data contoh</p>
            <pre class="border rounded p-3 small bg-light" data-template-preview style="white-space: pre-wrap;"></pre>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Simpan template</button>
                <button type="button" class="btn btn-outline-secondary" data-template-reset>Kembalikan ke bawaan</button>
            </div>
        </form>
    </div>

    <div class="modal fade" id="bankAccountModal" tabindex="-1" aria-labelledby="bankAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ $bankAction }}" class="modal-content" data-bank-form data-store-url="{{ $bankStoreUrl }}" novalidate>
                @csrf
                <input type="hidden" name="_method" value="{{ $bankMethod === 'PUT' ? 'PUT' : 'POST' }}" data-bank-method>
                <input type="hidden" name="bank_form_action" value="{{ $bankAction }}" data-bank-action-field>
                <input type="hidden" name="bank_form_method" value="{{ $bankMethod }}" data-bank-method-field>

                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="bankAccountModalLabel" data-bank-title>{{ $bankMethod === 'PUT' ? 'Ubah rekening bank' : 'Tambah rekening bank' }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="bank_name" class="form-label">Nama bank</label>
                        <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name') }}" maxlength="100" placeholder="BCA"
                            class="form-control @if ($bankErrors->has('bank_name')) is-invalid @endif" required>
                    </div>
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Nomor rekening</label>
                        <input type="text" id="account_number" name="account_number" value="{{ old('account_number') }}" inputmode="numeric" maxlength="40"
                            class="form-control @if ($bankErrors->has('account_number')) is-invalid @endif" required>
                        <div class="form-text">Hanya angka. Spasi dan tanda hubung dihapus otomatis.</div>
                    </div>
                    <div class="mb-3">
                        <label for="account_holder" class="form-label">Atas nama</label>
                        <input type="text" id="account_holder" name="account_holder" value="{{ old('account_holder') }}" maxlength="150"
                            class="form-control @if ($bankErrors->has('account_holder')) is-invalid @endif" required>
                    </div>
                    <div class="mb-3">
                        <label for="bank_sort_order" class="form-label">Urutan</label>
                        <input type="number" id="bank_sort_order" name="sort_order" min="0" value="{{ old('sort_order') }}"
                            class="form-control @if ($bankErrors->has('sort_order')) is-invalid @endif">
                        <div class="form-text">Kosongkan untuk menaruh di akhir daftar.</div>
                    </div>
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch">
                        <input type="checkbox" role="switch" id="bank_is_active" name="is_active" value="1" class="form-check-input"
                            @checked((bool) old('is_active', true))>
                        <label for="bank_is_active" class="form-check-label">Aktif (tampil di halaman pembayaran)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
