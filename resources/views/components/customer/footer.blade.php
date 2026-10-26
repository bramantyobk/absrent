<footer class="customer-footer">
    <div class="container py-4">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <x-brand :size="36" class="mb-3" />
                <p class="mb-1 fw-semibold">{{ $company->name }}</p>
                @if ($company->address !== '')
                    <p class="small text-body-secondary mb-0">{{ $company->address }}</p>
                @endif
            </div>

            <div class="col-6 col-lg-2">
                <p class="footer-heading">Menu</p>
                <ul class="list-unstyled small mb-0 d-grid gap-1">
                    <li><a href="{{ route('home') }}">Beranda</a></li>
                    <li><a href="{{ route('vehicles.index') }}">Kendaraan</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-3">
                <p class="footer-heading">Bantuan</p>
                @if ($supportUrl)
                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>WhatsApp support</a>
                    <p class="small text-body-secondary mt-2 mb-0">+{{ $company->support_whatsapp }}</p>
                @else
                    <p class="small text-body-secondary mb-0">Nomor support belum tersedia.</p>
                @endif
            </div>

            <div class="col-12 col-lg-3">
                <p class="footer-heading">Jam layanan kantor ({{ config('rental.timezone_label') }})</p>
                <p class="small text-body-secondary mb-2">Booking langsung di kantor. Pemesanan online, ambil, dan kembali dilayani 24 jam.</p>
                <dl class="footer-hours small mb-0">
                    @foreach ($hourGroups as $group)
                        <dt>{{ $group['label'] }}{{ $group['to'] !== $group['label'] ? ' - '.$group['to'] : '' }}</dt>
                        <dd>{{ $group['hours'] }}</dd>
                    @endforeach
                </dl>
            </div>
        </div>

        <p class="small text-body-secondary text-center border-top pt-3 mt-3 mb-0">&copy; {{ now()->year }} {{ $company->name }}</p>
    </div>
</footer>
