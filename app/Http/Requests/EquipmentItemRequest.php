<?php

namespace App\Http\Requests;

use App\Models\EquipmentItem;
use App\VehicleType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Aturan bersama untuk tambah dan ubah perlengkapan. Perlengkapan tidak dihapus, hanya dinonaktifkan.
 */
abstract class EquipmentItemRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return Gate::allows($this->ability(), $this->route('equipment') ?? EquipmentItem::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name')))]);

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
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique(EquipmentItem::class)
                    ->where('category', $this->input('category'))
                    ->ignore($this->route('equipment')),
            ],
            'category' => ['required', Rule::enum(VehicleType::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
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
            'name.unique' => 'Perlengkapan dengan nama ini sudah ada pada kategori tersebut.',
        ];
    }
}
