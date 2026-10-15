<?php

namespace App\Http\Requests;

use App\CancellationStatus;
use App\Models\CancellationRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CancellationRequestFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', CancellationRequest::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(CancellationStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
