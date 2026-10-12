<?php

namespace App\Http\Requests;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Weekday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', CompanyProfile::current());
    }

    protected function prepareForValidation(): void
    {
        $hours = collect($this->input('operating_hours'))
            ->map(fn (mixed $row): mixed => is_array($row)
                ? ['is_open' => filter_var($row['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN)] + $row
                : $row)
            ->all();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'support_whatsapp' => User::normalizePhone($this->input('support_whatsapp')),
            'operating_hours' => $hours,
        ]);
    }

    /**
     * Data tervalidasi dengan jam layanan tujuh hari berurutan. Jam hari libur dikosongkan.
     *
     * @return array<string, mixed>
     */
    public function companyAttributes(): array
    {
        $validated = $this->validated();
        $byDay = collect($validated['operating_hours'])->keyBy('day');

        $validated['operating_hours'] = array_map(function (Weekday $day) use ($byDay): array {
            $row = $byDay[$day->value];
            $isOpen = (bool) $row['is_open'];

            return [
                'day' => $day->value,
                'is_open' => $isOpen,
                'open_at' => $isOpen ? $row['open_at'] : null,
                'close_at' => $isOpen ? $row['close_at'] : null,
            ];
        }, Weekday::cases());

        return $validated;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:1000'],
            'support_whatsapp' => ['required', 'string', 'regex:/^62\d{8,13}$/'],
            'operating_hours' => ['required', 'array', 'size:7'],
            'operating_hours.*.day' => ['required', 'distinct', Rule::enum(Weekday::class)],
            'operating_hours.*.is_open' => ['required', 'boolean'],
            'operating_hours.*.open_at' => ['nullable', 'required_if_accepted:operating_hours.*.is_open', 'date_format:H:i'],
            'operating_hours.*.close_at' => ['nullable', 'required_if_accepted:operating_hours.*.is_open', 'date_format:H:i', 'after:operating_hours.*.open_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'support_whatsapp.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'operating_hours.size' => 'Jam layanan harus diisi untuk tujuh hari.',
            'operating_hours.*.open_at.required_if_accepted' => 'Jam buka wajib diisi untuk hari yang buka.',
            'operating_hours.*.close_at.required_if_accepted' => 'Jam tutup wajib diisi untuk hari yang buka.',
            'operating_hours.*.open_at.date_format' => 'Jam buka harus berformat JJ:MM.',
            'operating_hours.*.close_at.date_format' => 'Jam tutup harus berformat JJ:MM.',
            'operating_hours.*.close_at.after' => 'Jam tutup harus setelah jam buka.',
        ];
    }
}
