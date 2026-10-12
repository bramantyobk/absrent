<?php

namespace App\Http\Requests;

use App\Models\BankAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Aturan bersama untuk tambah dan ubah rekening bank. Rekening tidak dihapus, hanya dinonaktifkan.
 */
abstract class BankAccountRequest extends FormRequest
{
    public const ERROR_BAG = 'bank_account';

    protected $errorBag = self::ERROR_BAG;

    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return Gate::allows($this->ability(), $this->route('bank_account') ?? BankAccount::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_name' => preg_replace('/\s+/', ' ', trim((string) $this->input('bank_name'))),
            'account_number' => preg_replace('/[\s-]+/', '', (string) $this->input('account_number')),
            'account_holder' => preg_replace('/\s+/', ' ', trim((string) $this->input('account_holder'))),
        ]);

        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => [
                'required', 'regex:/^\d{5,30}$/',
                Rule::unique(BankAccount::class)
                    ->where('bank_name', $this->input('bank_name'))
                    ->ignore($this->route('bank_account')),
            ],
            'account_holder' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_number.regex' => 'Nomor rekening hanya boleh berisi angka (5 sampai 30 digit).',
            'account_number.unique' => 'Rekening ini sudah terdaftar pada bank tersebut.',
        ];
    }
}
