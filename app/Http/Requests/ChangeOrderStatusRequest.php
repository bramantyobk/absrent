<?php

namespace App\Http\Requests;

use App\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChangeOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', $this->route('order'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([OrderStatus::Rented->value, OrderStatus::Completed->value])],
        ];
    }
}
