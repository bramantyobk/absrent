<footer class="customer-footer mt-5">
    <div class="container py-4 py-lg-5">
        <div class="row g-4">
            <div class="col-12 col-lg-5">
                <x-brand :size="36" class="mb-3" />
                <p class="mb-1 fw-semibold">{{ $company->name }}</p>
                @if ($company->address !== '')
                    <p class="small text-body-secondary mb-0">{{ $company->address }}</p>
                @endif
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <p class="footer-heading">Bantuan</p>
                @if ($supportUrl)
                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">WhatsApp support</a>
                    <p class="small text-body-secondary mt-2 mb-0">+{{ $company->support_whatsapp }}</p>
                @else
                    <p class="small text-body-secondary mb-0">Nomor support belum tersedia.</p>
                @endif
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <p class="footer-heading">Jam layanan kantor ({{ config('rental.timezone_label') }})</p>
                <p class="small text-body-secondary mb-2">Booking langsung di kantor. Pemesanan online, ambil, dan kembali dilayani 24 jam.</p>
                <dl class="footer-hours small mb-0">
                    @foreach ($hours as $day)
                        <dt>{{ $day['label'] }}</dt>
                        <dd>{{ $day['is_open'] ? "{$day['open_at']} - {$day['close_at']}" : 'Libur' }}</dd>
                    @endforeach
                </dl>
            </div>
        </div>

        <p class="small text-body-secondary text-center border-top pt-3 mt-4 mb-0">&copy; {{ now()->year }} {{ $company->name }}</p>
    </div>
</footer>
