<?php

namespace App\Http\Requests;

use App\VehicleType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class RevenueChartRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 366;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(VehicleType::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->startDate()->diffInDays($this->endDate()) > self::MAX_RANGE_DAYS) {
                        $fail('Rentang tanggal maksimal '.self::MAX_RANGE_DAYS.' hari.');
                    }
                },
            ],
        ];
    }

    public function vehicleType(): ?VehicleType
    {
        return VehicleType::tryFrom((string) $this->validated('type'));
    }

    public function startDate(): Carbon
    {
        return Carbon::parse($this->input('start_date', now()->subDays(29)))->startOfDay();
    }

    public function endDate(): Carbon
    {
        return Carbon::parse($this->input('end_date', now()))->endOfDay();
    }
}
