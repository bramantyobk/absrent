@props(['vehicle', 'startAt' => null, 'days' => null])

<article class="vehicle-card h-100">
    <a href="{{ route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => $startAt, 'days' => $days]) }}" class="vehicle-card-media">
        <img src="{{ $vehicle->photo_url }}" alt="{{ $vehicle->brand }} {{ $vehicle->model }}" loading="lazy">
    </a>
    <div class="vehicle-card-body">
        <div class="d-flex flex-wrap gap-1 mb-2">
            <span class="vehicle-chip"><i class="bi bi-{{ $vehicle->type->value === 'car' ? 'car-front' : 'scooter' }} me-1" aria-hidden="true"></i>{{ $vehicle->type->label() }}</span>
            @if ($vehicle->drive_type)
                <span class="vehicle-chip">{{ $vehicle->drive_type->label() }}</span>
            @endif
        </div>
        <h3 class="vehicle-card-title">
            <a href="{{ route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => $startAt, 'days' => $days]) }}">{{ $vehicle->brand }} {{ $vehicle->model }}</a>
        </h3>
        <p class="vehicle-card-meta">{{ $vehicle->year }} · {{ $vehicle->color }}</p>
        <div class="d-flex align-items-end justify-content-between gap-2 mt-auto">
            <p class="vehicle-card-price mb-0">
                <strong>Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}</strong>
                <span>/ hari</span>
            </p>
            <a href="{{ route('vehicles.show', ['vehicle' => $vehicle, 'start_at' => $startAt, 'days' => $days]) }}" class="btn btn-primary btn-sm">Pesan</a>
        </div>
    </div>
</article>
