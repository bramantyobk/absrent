@php($isRented = $order->status === \App\OrderStatus::Rented)
@php($unit = $returnUnits->get($item->id))
@php($isOpenedByError = (int) old('change_item_id') === $item->id)
@php($old = fn (string $key, $default = null) => $isOpenedByError ? old($key, $default) : $default)

<div class="modal fade" id="changeModal{{ $item->id }}" tabindex="-1" aria-labelledby="changeModal{{ $item->id }}Label" aria-hidden="true"
    data-vehicle-change data-days="{{ $item->days }}" data-old-price="{{ $item->unit_price }}" data-old-type="{{ $item->vehicle->type->value }}"
    data-order-total="{{ $order->total_price }}" data-threshold="{{ $largeReductionThreshold }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <form method="POST" action="{{ route('dashboard.orders.items.vehicle-change.store', [$order, $item]) }}" class="modal-content" data-confirm-summary data-summary-title="Kendaraan {{ $item->vehicle->brand }} {{ $item->vehicle->model }} akan diganti." novalidate>
            @csrf
            <input type="hidden" name="change_item_id" value="{{ $item->id }}">

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="changeModal{{ $item->id }}Label">Ganti kendaraan: {{ $item->vehicle->brand }} {{ $item->vehicle->model }}</h2>
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
                    <div class="col-12 col-md-6">
                        <label for="change_reason_{{ $item->id }}" class="form-label">Alasan</label>
                        <select id="change_reason_{{ $item->id }}" name="reason" data-summary-group="Penggantian" data-summary-label="Alasan" class="form-select" data-change-reason required>
                            <option value="">Pilih alasan</option>
                            @foreach ($changeReasons as $reason)
                                <option value="{{ $reason->value }}" data-same-category="{{ $reason->requiresSameCategory() ? '1' : '0' }}" @selected($old('reason') === $reason->value)>{{ $reason->label() }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Selain Permintaan Pelanggan, unit pengganti harus sekategori ({{ $item->vehicle->type->label() }}).</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="change_vehicle_{{ $item->id }}" class="form-label">Unit pengganti</label>
                        <select id="change_vehicle_{{ $item->id }}" name="vehicle_id" data-summary-group="Penggantian" data-summary-label="Unit pengganti" class="form-select" data-change-vehicle required>
                            <option value="">Pilih unit yang kosong</option>
                            @foreach ($replacements->get($item->id, collect()) as $vehicle)
                                <option value="{{ $vehicle->id }}" data-type="{{ $vehicle->type->value }}" data-price="{{ $vehicle->price_per_day }}" data-odometer="{{ $vehicle->odometer_km ?? 0 }}"
                                    @selected((int) $old('vehicle_id') === $vehicle->id)>
                                    {{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->plate_number }}) &middot; {{ $vehicle->type->label() }} &middot; Rp{{ number_format($vehicle->price_per_day, 0, ',', '.') }}/hari
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Hanya unit yang kosong pada {{ $isRented ? 'sisa rentang sewa (dari sekarang)' : 'rentang sewa' }}, termasuk jeda.</div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3 bg-light small" data-change-suggestion>
                            <div class="d-flex justify-content-between"><span>Tarif lama</span><strong>Rp{{ number_format($item->unit_price, 0, ',', '.') }}/hari</strong></div>
                            <div class="d-flex justify-content-between"><span>Tarif baru</span><strong data-suggest-new>-</strong></div>
                            <div class="d-flex justify-content-between"><span>Jumlah hari</span><strong>{{ $item->days }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Selisih saran (<span data-suggest-label>-</span>)</span><strong data-suggest-amount>-</strong></div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between"><span>Total pesanan sebelum</span><strong>Rp{{ number_format($order->total_price, 0, ',', '.') }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Total pesanan sesudah</span><strong data-suggest-total>-</strong></div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="change_amount_{{ $item->id }}" class="form-label">Nominal akhir (Rp)</label>
                        <input type="number" step="1" id="change_amount_{{ $item->id }}" name="final_amount" data-summary-group="Penggantian" data-summary-label="Nominal akhir (Rp)" value="{{ $old('final_amount') }}" data-change-amount
                            class="form-control" required>
                        <div class="form-text">
                            Positif = tambah bayar (dibayar di tempat), negatif = pengurangan (dikembalikan di luar aplikasi), 0 = tanpa selisih.
                            Pengurangan Rp{{ number_format($largeReductionThreshold, 0, ',', '.') }} atau lebih hanya admin.
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="change_note_{{ $item->id }}" class="form-label">Catatan penggantian</label>
                        <textarea id="change_note_{{ $item->id }}" name="note" data-summary-group="Penggantian" data-summary-label="Catatan penggantian" rows="3" maxlength="1000" class="form-control">{{ $old('note') }}</textarea>
                    </div>

                    @if ($isRented && $unit)
                        <div class="col-12 border-top pt-3">
                            <h3 class="fs-6 fw-semibold">Unit lama: {{ $item->vehicle->brand }} {{ $item->vehicle->model }}</h3>
                            @foreach (\App\OldVehicleStatus::cases() as $status)
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="old_vehicle[status]" data-summary-group="Unit lama" data-summary-label="Nasib unit lama" data-summary-text="{{ $status->label() }}" id="old_status_{{ $item->id }}_{{ $status->value }}" value="{{ $status->value }}"
                                        data-old-status @checked($old('old_vehicle.status', 'returned') === $status->value)>
                                    <label class="form-check-label" for="old_status_{{ $item->id }}_{{ $status->value }}">
                                        {{ $status->label() }}
                                        <span class="text-body-secondary small">
                                            @if ($status === \App\OldVehicleStatus::Returned) &ndash; dikembalikan di tempat, isi inspeksi pengembalian, unit langsung bebas
                                            @elseif ($status === \App\OldVehicleStatus::Pending) &ndash; inspeksi menyusul, unit tetap menahan jadwal
                                            @else &ndash; unit disembunyikan dari katalog sampai ditangani admin @endif
                                        </span>
                                    </label>
                                </div>
                            @endforeach

                            <div class="mt-3" data-old-section="returned">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label" for="old_odometer_{{ $item->id }}">Odometer (km)</label>
                                        <input type="number" step="1" min="{{ $unit['minimum_odometer'] }}" id="old_odometer_{{ $item->id }}" name="old_vehicle[inspection][odometer_km]" data-summary-group="Unit lama: inspeksi pengembalian" data-summary-label="Odometer (km)"
                                            value="{{ $old('old_vehicle.inspection.odometer_km', $unit['minimum_odometer']) }}" class="form-control">
                                        <div class="form-text">Minimal {{ number_format($unit['minimum_odometer'], 0, ',', '.') }} km.</div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="old_energy_{{ $item->id }}">{{ $unit['energy_label'] }} (%)</label>
                                        <input type="number" step="1" min="0" max="100" id="old_energy_{{ $item->id }}" name="old_vehicle[inspection][energy_level]" data-summary-group="Unit lama: inspeksi pengembalian" data-summary-label="{{ $unit['energy_label'] }} (%)"
                                            value="{{ $old('old_vehicle.inspection.energy_level', 50) }}" class="form-control">
                                    </div>
                                    @if ($unit['checklist'] !== [])
                                        <div class="col-12">
                                            <p class="form-label mb-1">Ceklis perlengkapan dikembalikan</p>
                                            @foreach ($unit['checklist'] as $row)
                                                <div class="d-flex align-items-center justify-content-between gap-3 py-1">
                                                    <label class="mb-0" for="old_eq_{{ $item->id }}_{{ $row['id'] }}">{{ $row['name'] }} <span class="text-body-secondary small">(seharusnya {{ $row['expected'] }})</span></label>
                                                    <input type="number" min="0" max="99" step="1" id="old_eq_{{ $item->id }}_{{ $row['id'] }}" name="old_vehicle[inspection][equipment][{{ $row['id'] }}]" data-summary-group="Unit lama: inspeksi pengembalian" data-summary-equipment data-name="{{ $row['name'] }}" data-expected="{{ $row['expected'] }}"
                                                        value="{{ $old("old_vehicle.inspection.equipment.{$row['id']}", $row['default']) }}" class="form-control form-control-sm" style="width: 5.5rem">
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="col-12">
                                        <label class="form-label" for="old_inspection_note_{{ $item->id }}">Catatan inspeksi</label>
                                        <textarea id="old_inspection_note_{{ $item->id }}" name="old_vehicle[inspection][note]" data-summary-group="Unit lama: inspeksi pengembalian" data-summary-label="Catatan" rows="2" maxlength="1000" class="form-control">{{ $old('old_vehicle.inspection.note') }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 d-none" data-old-section="unreturned">
                                <label class="form-label" for="old_note_{{ $item->id }}">Catatan unit lama</label>
                                <textarea id="old_note_{{ $item->id }}" name="old_vehicle[note]" data-summary-group="Unit lama" data-summary-label="Catatan unit lama" rows="2" maxlength="1000" class="form-control" placeholder="Alasan ditunda atau kronologi hilang/dicuri">{{ $old('old_vehicle.note') }}</textarea>
                            </div>
                        </div>

                        <div class="col-12 border-top pt-3">
                            <h3 class="fs-6 fw-semibold">Unit baru: inspeksi pengambilan</h3>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="pickup_odometer_{{ $item->id }}">Odometer (km)</label>
                                    <input type="number" step="1" min="0" id="pickup_odometer_{{ $item->id }}" name="pickup[odometer_km]" data-summary-group="Unit baru: inspeksi pengambilan" data-summary-label="Odometer (km)" value="{{ $old('pickup.odometer_km') }}" data-pickup-odometer class="form-control">
                                    <div class="form-text" data-pickup-minimum>Pilih unit pengganti dahulu.</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="pickup_energy_{{ $item->id }}">Bensin/Baterai (%)</label>
                                    <input type="number" step="1" min="0" max="100" id="pickup_energy_{{ $item->id }}" name="pickup[energy_level]" data-summary-group="Unit baru: inspeksi pengambilan" data-summary-label="Bensin/Baterai (%)" value="{{ $old('pickup.energy_level', 100) }}" class="form-control">
                                </div>
                                @foreach ($pickupChecklists as $category => $rows)
                                    <div class="col-12 d-none" data-pickup-checklist="{{ $category }}">
                                        <p class="form-label mb-1">Ceklis perlengkapan ({{ \App\VehicleType::from($category)->label() }})</p>
                                        @foreach ($rows as $row)
                                            <div class="d-flex align-items-center justify-content-between gap-3 py-1">
                                                <label class="mb-0" for="pickup_eq_{{ $item->id }}_{{ $row['id'] }}">{{ $row['name'] }} <span class="text-body-secondary small">(seharusnya {{ $row['expected'] }})</span></label>
                                                <input type="number" min="0" max="99" step="1" id="pickup_eq_{{ $item->id }}_{{ $row['id'] }}" name="pickup[equipment][{{ $row['id'] }}]" data-summary-group="Unit baru: inspeksi pengambilan" data-summary-equipment data-name="{{ $row['name'] }}" data-expected="{{ $row['expected'] }}"
                                                    value="{{ $old("pickup.equipment.{$row['id']}", $row['default']) }}" class="form-control form-control-sm" style="width: 5.5rem" disabled>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                                <div class="col-12">
                                    <label class="form-label" for="pickup_note_{{ $item->id }}">Catatan inspeksi</label>
                                    <textarea id="pickup_note_{{ $item->id }}" name="pickup[note]" data-summary-group="Unit baru: inspeksi pengambilan" data-summary-label="Catatan" rows="2" maxlength="1000" class="form-control">{{ $old('pickup.note') }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan penggantian</button>
            </div>
        </form>
    </div>
</div>
