<?php

namespace App\Http\Requests;

use App\TransactionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Verifikasi pembayaran oleh staf: Lunas (bukti wajib, jpg/png maksimal 2 MB) atau Gagal (catatan wajib).
 * Staf dapat mengoreksi rekening tujuan yang dipilih pelanggan.
 */
class VerifyTransactionRequest extends FormRequest
{
    public const MAX_PROOF_KB = 2048;

    public function authorize(): bool
    {
        return Gate::allows('verify', $this->route('transaction'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([TransactionStatus::Paid->value, TransactionStatus::Failed->value])],
            'proof' => ['exclude_unless:status,paid', 'required', 'file', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_PROOF_KB],
            'note' => ['exclude_unless:status,failed', 'required', 'string', 'max:1000'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Pilih Lunas atau Gagal.',
            'proof.required' => 'Bukti pembayaran wajib diunggah untuk menetapkan Lunas.',
            'proof.mimes' => 'Bukti pembayaran harus berformat JPG atau PNG.',
            'proof.max' => 'Bukti pembayaran maksimal 2 MB.',
            'note.required' => 'Catatan alasan wajib diisi untuk menetapkan Gagal.',
        ];
    }
}
