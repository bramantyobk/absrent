@php($isOpenedByError = (int) old('edit_inspection_id') === $inspection->id)
@php($old = fn (string $key, $default = null) => $isOpenedByError ? old($key, $default) : $default)
@php($inspectedVehicle = $inspection->vehicle)

<div class="modal fade" id="editInspectionModal{{ $inspection->id }}" tabindex="-1" aria-labelledby="editInspectionModal{{ $inspection->id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('dashboard.inspections.update', $inspection) }}" class="modal-content" novalidate>
            @csrf
            @method('PATCH')
            <input type="hidden" name="edit_inspection_id" value="{{ $inspection->id }}">

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="editInspectionModal{{ $inspection->id }}Label">
                    Edit inspeksi {{ strtolower($inspection->type->label()) }}: {{ $inspectedVehicle->brand }} {{ $inspectedVehicle->model }}
                </h2>
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

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="edit_odometer_{{ $inspection->id }}">Odometer (km)</label>
                        <input type="number" step="1" min="0" id="edit_odometer_{{ $inspection->id }}" name="odometer_km"
                            value="{{ $old('odometer_km', $inspection->odometer_km) }}" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="edit_energy_{{ $inspection->id }}">{{ $inspectedVehicle->drive_type?->energyLabel() ?? 'Bensin' }} (%)</label>
                        <input type="number" step="1" min="0" max="100" id="edit_energy_{{ $inspection->id }}" name="energy_level"
                            value="{{ $old('energy_level', $inspection->energy_level) }}" class="form-control" required>
                    </div>

                    @if ($inspection->equipment_checks)
                        <div class="col-12">
                            <p class="form-label mb-1">Ceklis perlengkapan</p>
                            @foreach ($inspection->equipment_checks as $row)
                                <div class="d-flex align-items-center justify-content-between gap-3 py-1">
                                    <label class="mb-0" for="edit_eq_{{ $inspection->id }}_{{ $row['equipment_item_id'] }}">{{ $row['name'] }} <span class="text-body-secondary small">(seharusnya {{ $row['expected'] }})</span></label>
                                    <input type="number" min="0" max="99" step="1" id="edit_eq_{{ $inspection->id }}_{{ $row['equipment_item_id'] }}"
                                        name="equipment[{{ $row['equipment_item_id'] }}]"
                                        value="{{ $old("equipment.{$row['equipment_item_id']}", $row['checked']) }}" class="form-control form-control-sm" style="width: 5.5rem" required>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label" for="edit_note_{{ $inspection->id }}">Catatan</label>
                        <textarea id="edit_note_{{ $inspection->id }}" name="note" rows="2" maxlength="1000" class="form-control">{{ $old('note', $inspection->note) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan perubahan</button>
            </div>
        </form>
    </div>
</div>
