<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CustomerLookupRequest extends FormRequest
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
        return [
            'phone' => ['nullable', 'string', 'max:30', 'required_without:q'],
            'q' => ['nullable', 'string', 'min:2', 'max:100', 'required_without:phone'],
        ];
    }
}
