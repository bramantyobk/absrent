@php($change = $pending['change'])
@php($isOpenedByError = (int) old('old_unit_change_id') === $change->id)
@php($old = fn (string $key, $default = null) => $isOpenedByError ? old($key, $default) : $default)

<div class="modal fade" id="oldUnitModal{{ $change->id }}" tabindex="-1" aria-labelledby="oldUnitModal{{ $change->id }}Label" aria-hidden="true" data-old-unit>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('dashboard.vehicle-changes.old-vehicle.update', $change) }}" class="modal-content" novalidate>
            @csrf
            @method('PATCH')
            <input type="hidden" name="old_unit_change_id" value="{{ $change->id }}">

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="oldUnitModal{{ $change->id }}Label">Selesaikan unit lama: {{ $change->fromVehicle->brand }} {{ $change->fromVehicle->model }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                @if ($isOpenedByError && $errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-check">
                    <input type="radio" class="form-check-input" name="action" id="resolve_returned_{{ $change->id }}" value="returned" data-resolve-action @checked($old('action', 'returned') === 'returned')>
                    <label class="form-check-label" for="resolve_returned_{{ $change->id }}">Isi inspeksi pengembalian (unit langsung bebas)</label>
                </div>
                <div class="form-check mb-3">
                    <input type="radio" class="form-check-input" name="action" id="resolve_lost_{{ $change->id }}" value="lost" data-resolve-action @checked($old('action') === 'lost')>
                    <label class="form-check-label" for="resolve_lost_{{ $change->id }}">Tandai hilang/dicuri (disembunyikan dari katalog)</label>
                </div>

                <div data-resolve-section="returned">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="resolve_odometer_{{ $change->id }}">Odometer (km)</label>
                            <input type="number" step="1" min="{{ $pending['minimum_odometer'] }}" id="resolve_odometer_{{ $change->id }}" name="inspection[odometer_km]"
                                value="{{ $old('inspection.odometer_km', $pending['minimum_odometer']) }}" class="form-control">
                            <div class="form-text">Minimal {{ number_format($pending['minimum_odometer'], 0, ',', '.') }} km.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="resolve_energy_{{ $change->id }}">{{ $pending['energy_label'] }} (%)</label>
                            <input type="number" step="1" min="0" max="100" id="resolve_energy_{{ $change->id }}" name="inspection[energy_level]"
                                value="{{ $old('inspection.energy_level', 50) }}" class="form-control">
                        </div>
                        @if ($pending['checklist'] !== [])
                            <div class="col-12">
                                <p class="form-label mb-1">Ceklis perlengkapan dikembalikan</p>
                                @foreach ($pending['checklist'] as $row)
                                    <div class="d-flex align-items-center justify-content-between gap-3 py-1">
                                        <label class="mb-0" for="resolve_eq_{{ $change->id }}_{{ $row['id'] }}">{{ $row['name'] }} <span class="text-body-secondary small">(seharusnya {{ $row['expected'] }})</span></label>
                                        <input type="number" min="0" max="99" step="1" id="resolve_eq_{{ $change->id }}_{{ $row['id'] }}" name="inspection[equipment][{{ $row['id'] }}]"
                                            value="{{ $old("inspection.equipment.{$row['id']}", $row['default']) }}" class="form-control form-control-sm" style="width: 5.5rem">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label" for="resolve_inspection_note_{{ $change->id }}">Catatan inspeksi</label>
                            <textarea id="resolve_inspection_note_{{ $change->id }}" name="inspection[note]" rows="2" maxlength="1000" class="form-control">{{ $old('inspection.note') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-none" data-resolve-section="lost">
                    <label class="form-label" for="resolve_note_{{ $change->id }}">Catatan</label>
                    <textarea id="resolve_note_{{ $change->id }}" name="note" rows="3" maxlength="1000" class="form-control">{{ $old('note') }}</textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
