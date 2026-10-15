<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use App\TransactionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransactionFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Transaction::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TransactionStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
