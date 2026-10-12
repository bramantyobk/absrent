<?php

namespace App\Http\Requests;

use App\Actions\Vehicles\EnsureScheduleIsFree;
use App\Models\VehicleSchedule;
use App\VehicleScheduleType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Aturan bersama untuk tambah dan ubah jadwal servis. Aturan bentrok dijaga di Action.
 */
abstract class VehicleScheduleRequest extends FormRequest
{
    protected $errorBag = EnsureScheduleIsFree::ERROR_BAG;

    abstract protected function ability(): string;

    public function authorize(): bool
    {
        $schedule = $this->route('schedule');

        return Gate::allows($this->ability(), $schedule ?? VehicleSchedule::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(VehicleScheduleType::class)],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'note' => ['nullable', 'string', 'max:255'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_at.after' => 'Waktu selesai harus setelah waktu mulai.',
            'start_at.after_or_equal' => 'Waktu mulai tidak boleh di masa lalu.',
        ];
    }
}
