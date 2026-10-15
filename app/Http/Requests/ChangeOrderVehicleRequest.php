<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\OldVehicleStatus;
use App\OrderStatus;
use App\VehicleChangeReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Ganti kendaraan pada satu item pesanan. Dari pesanan Disewa wajib menyertakan nasib unit lama (`old_vehicle`) dan inspeksi
 * pengambilan unit baru (`pickup`). Pengurangan biaya sebesar batas atau lebih hanya oleh admin.
 */
class ChangeOrderVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        if (! Gate::allows('manage', $order)) {
            return false;
        }

        $isLargeReduction = is_numeric($this->input('final_amount')) && (int) $this->input('final_amount') <= -config('rental.vehicle_change_admin_threshold');

        return ! $isLargeReduction || Gate::allows('approveLargeReduction', $order);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isRented = fn (): bool => $this->route('order')->status === OrderStatus::Rented;
        $oldStatus = fn (): ?string => $this->input('old_vehicle.status');
        $returned = fn (): bool => $isRented() && $oldStatus() === OldVehicleStatus::Returned->value;
        $unreturned = fn (): bool => $isRented() && in_array($oldStatus(), [OldVehicleStatus::Pending->value, OldVehicleStatus::Lost->value], true);

        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'reason' => ['required', Rule::enum(VehicleChangeReason::class)],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn (): bool => $this->input('reason') === VehicleChangeReason::Other->value)],
            'final_amount' => ['required', 'integer', 'between:-50000000,50000000'],

            'old_vehicle.status' => [Rule::requiredIf($isRented), Rule::enum(OldVehicleStatus::class)],
            'old_vehicle.note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($unreturned)],
            'old_vehicle.inspection.odometer_km' => [Rule::requiredIf($returned), 'integer', 'min:0', 'max:9999999'],
            'old_vehicle.inspection.energy_level' => [Rule::requiredIf($returned), 'integer', 'between:0,100'],
            'old_vehicle.inspection.note' => ['nullable', 'string', 'max:1000'],
            'old_vehicle.inspection.equipment' => ['nullable', 'array'],
            'old_vehicle.inspection.equipment.*' => ['required', 'integer', 'between:0,99'],

            'pickup.odometer_km' => [Rule::requiredIf($isRented), 'integer', 'min:0', 'max:9999999'],
            'pickup.energy_level' => [Rule::requiredIf($isRented), 'integer', 'between:0,100'],
            'pickup.note' => ['nullable', 'string', 'max:1000'],
            'pickup.equipment' => ['nullable', 'array'],
            'pickup.equipment.*' => ['required', 'integer', 'between:0,99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Pilih kendaraan pengganti.',
            'reason.required' => 'Pilih alasan penggantian.',
            'note.required' => 'Catatan wajib diisi untuk alasan Lainnya.',
            'final_amount.required' => 'Nominal akhir wajib diisi (isi 0 bila tanpa selisih).',
            'final_amount.integer' => 'Nominal akhir harus berupa angka bulat rupiah.',
            'old_vehicle.status.required' => 'Tentukan nasib unit lama.',
            'old_vehicle.note.required' => 'Catatan wajib diisi untuk unit lama yang ditunda atau hilang/dicuri.',
            'old_vehicle.inspection.odometer_km.required' => 'Odometer unit lama wajib diisi.',
            'old_vehicle.inspection.energy_level.required' => 'Level energi unit lama wajib diisi.',
            'pickup.odometer_km.required' => 'Odometer unit baru wajib diisi.',
            'pickup.energy_level.required' => 'Level energi unit baru wajib diisi.',
        ];
    }

    /**
     * Data tervalidasi untuk Action: unit lama dan inspeksi hanya dipakai pada pesanan Disewa.
     *
     * @return array<string, mixed>
     */
    public function changeData(): array
    {
        $data = $this->validated();

        if ($this->route('order')->status !== OrderStatus::Rented) {
            unset($data['old_vehicle'], $data['pickup']);
        }

        return $data;
    }
}
