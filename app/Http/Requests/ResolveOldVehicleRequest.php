<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\OldVehicleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Menyelesaikan unit lama yang Ditunda: isi inspeksi pengembalian (`returned`) atau tandai hilang/dicuri (`lost`, catatan wajib).
 */
class ResolveOldVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', Order::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $returned = fn (): bool => $this->input('action') === OldVehicleStatus::Returned->value;

        return [
            'action' => ['required', Rule::in([OldVehicleStatus::Returned->value, OldVehicleStatus::Lost->value])],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn (): bool => $this->input('action') === OldVehicleStatus::Lost->value)],
            'inspection.odometer_km' => [Rule::requiredIf($returned), 'integer', 'min:0', 'max:9999999'],
            'inspection.energy_level' => [Rule::requiredIf($returned), 'integer', 'between:0,100'],
            'inspection.note' => ['nullable', 'string', 'max:1000'],
            'inspection.equipment' => ['nullable', 'array'],
            'inspection.equipment.*' => ['required', 'integer', 'between:0,99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Pilih isi inspeksi pengembalian atau tandai hilang/dicuri.',
            'note.required' => 'Catatan wajib diisi untuk unit hilang/dicuri.',
            'inspection.odometer_km.required' => 'Odometer wajib diisi.',
            'inspection.energy_level.required' => 'Level energi wajib diisi.',
        ];
    }
}
