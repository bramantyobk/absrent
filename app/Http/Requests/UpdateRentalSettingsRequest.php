<?php

namespace App\Http\Requests;

use App\Models\RentalSetting;
use App\Services\RentalSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateRentalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', RentalSetting::current());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (RentalSettings::FIELDS as $key => $field) {
            $rules[$key] = ['required', 'integer', "min:{$field['min']}", "max:{$field['max']}"];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_map(fn (array $field): string => mb_strtolower($field['label']), RentalSettings::FIELDS);
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['min_rental_hours', 'max_rental_days', 'verification_warning_hours', 'confirm_validation_window_hours'])) {
                    return;
                }

                if ((int) ceil($this->integer('min_rental_hours') / 24) > $this->integer('max_rental_days')) {
                    $validator->errors()->add('min_rental_hours', 'Durasi sewa minimal tidak boleh melebihi durasi sewa maksimal.');
                }

                if ($this->integer('verification_warning_hours') > $this->integer('confirm_validation_window_hours')) {
                    $validator->errors()->add('verification_warning_hours', 'Peringatan tidak boleh melebihi batas verifikasi pembayaran.');
                }
            },
        ];
    }

    /**
     * @return array<string, int>
     */
    public function settings(): array
    {
        return array_map('intval', $this->safe()->only(array_keys(RentalSettings::FIELDS)));
    }
}
