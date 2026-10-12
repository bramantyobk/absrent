<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Auth\RegisterRequest as WebRegisterRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class RegisterRequest extends WebRegisterRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + ['device_name' => ['required', 'string', 'max:100']];
    }
}
