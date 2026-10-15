<?php

namespace App\Http\Requests;

use App\CancellationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Keputusan staf atas pengajuan pembatalan. Catatan wajib: pada persetujuan berisi info pengembalian dana.
 */
class HandleCancellationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('handle', $this->route('cancellationRequest'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([CancellationStatus::Approved->value, CancellationStatus::Rejected->value])],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.in' => 'Pilih setujui atau tolak.',
            'note.required' => 'Catatan keputusan wajib diisi (pada persetujuan, isi info pengembalian dana).',
        ];
    }
}
