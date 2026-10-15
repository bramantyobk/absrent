@php($isPickup = $inspectionType === \App\InspectionType::Pickup)
@php($targetStatus = $isPickup ? 'rented' : 'completed')

<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <form method="POST" action="{{ route('dashboard.orders.status.update', $order) }}" class="modal-content" novalidate>
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $targetStatus }}">

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="statusModalLabel">
                    {{ $isPickup ? 'Inspeksi pengambilan, ubah menjadi Disewa' : 'Inspeksi pengembalian, ubah menjadi Selesai' }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                @foreach ($inspectionUnits as $unit)
                    @php($item = $unit['item'])
                    @php($prefix = "inspections.{$item->id}")
                    @php($energy = (int) old("{$prefix}.energy_level", $isPickup ? 100 : 50))

                    <fieldset class="{{ $loop->first ? '' : 'border-top pt-3 mt-3' }}" data-inspection-unit>
                        <legend class="fs-6 fw-semibold">
                            {{ $item->vehicle->brand }} {{ $item->vehicle->model }}
                            <span class="text-body-secondary fw-normal">({{ $item->vehicle->plate_number }})</span>
                        </legend>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="odometer_{{ $item->id }}" class="form-label">Odometer (km)</label>
                                <input type="number" min="{{ $unit['minimum_odometer'] }}" step="1" id="odometer_{{ $item->id }}"
                                    name="inspections[{{ $item->id }}][odometer_km]"
                                    value="{{ old("{$prefix}.odometer_km", $unit['minimum_odometer']) }}"
                                    class="form-control @error("{$prefix}.odometer_km") is-invalid @enderror" required>
                                <div class="form-text">Minimal {{ number_format($unit['minimum_odometer'], 0, ',', '.') }} km.</div>
                                @error("{$prefix}.odometer_km")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="energy_{{ $item->id }}" class="form-label">{{ $unit['energy_label'] }} (%)</label>
                                <input type="number" min="0" max="100" step="1" id="energy_{{ $item->id }}"
                                    name="inspections[{{ $item->id }}][energy_level]" value="{{ $energy }}" data-energy-input
                                    class="form-control @error("{$prefix}.energy_level") is-invalid @enderror" required>
                                @error("{$prefix}.energy_level")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    @foreach (['Kosong' => 0, '¼' => 25, '½' => 50, '¾' => 75, 'Penuh' => 100] as $label => $level)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-energy-quick="{{ $level }}">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>

                            @if ($unit['checklist'] !== [])
                                <div class="col-12">
                                    <p class="form-label mb-1">Ceklis perlengkapan ({{ $isPickup ? 'dibawa pelanggan' : 'dikembalikan' }})</p>
                                    @foreach ($unit['checklist'] as $row)
                                        <div class="d-flex align-items-center justify-content-between gap-3 py-1">
                                            <label for="equipment_{{ $item->id }}_{{ $row['id'] }}" class="mb-0">{{ $row['name'] }} <span class="text-body-secondary small">(seharusnya {{ $row['expected'] }})</span></label>
                                            <input type="number" min="0" max="99" step="1" id="equipment_{{ $item->id }}_{{ $row['id'] }}"
                                                name="inspections[{{ $item->id }}][equipment][{{ $row['id'] }}]"
                                                value="{{ old("{$prefix}.equipment.{$row['id']}", $row['default']) }}"
                                                class="form-control form-control-sm" style="width: 5.5rem" required>
                                        </div>
                                    @endforeach
                                    @error("{$prefix}.equipment")
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                    @error("{$prefix}.equipment.*")
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <div class="col-12">
                                <label for="note_{{ $item->id }}" class="form-label">Catatan</label>
                                <textarea id="note_{{ $item->id }}" name="inspections[{{ $item->id }}][note]" rows="2" maxlength="1000"
                                    class="form-control">{{ old("{$prefix}.note") }}</textarea>
                            </div>
                        </div>
                    </fieldset>
                @endforeach
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan inspeksi dan ubah status</button>
            </div>
        </form>
    </div>
</div>
