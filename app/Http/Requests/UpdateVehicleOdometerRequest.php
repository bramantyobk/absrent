<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateVehicleOdometerRequest extends FormRequest
{
    public const ERROR_BAG = 'odometer';

    protected $errorBag = self::ERROR_BAG;

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('vehicle'));
    }

    /**
     * Nilai tidak boleh lebih kecil dari odometer terakhir. Koreksi mundur hanya oleh admin dan wajib bercatatan.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');
        $lastOdometer = $vehicle->odometer_km ?? 0;
        $isCorrection = $this->isCorrection($lastOdometer);

        return [
            'odometer_km' => [
                'required',
                'integer',
                'max:9999999',
                $this->user()->isAdmin() ? 'min:0' : "min:{$lastOdometer}",
            ],
            'note' => [Rule::requiredIf($isCorrection), 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return [
            'odometer_km.min' => 'Odometer tidak boleh lebih kecil dari odometer terakhir ('.number_format($vehicle->odometer_km ?? 0, 0, ',', '.').' km). Koreksi mundur hanya oleh admin.',
            'note.required' => 'Catatan wajib diisi untuk koreksi odometer ke nilai yang lebih kecil.',
        ];
    }

    private function isCorrection(int $lastOdometer): bool
    {
        $input = $this->input('odometer_km');

        return is_numeric($input) && (int) $input < $lastOdometer;
    }
}
