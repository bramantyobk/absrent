<?php

namespace App\Http\Requests;

use App\VehicleDriveType;
use App\VehicleType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Aturan bersama untuk tambah dan ubah kendaraan. Otorisasi dan aturan unik plat ditentukan oleh turunannya.
 */
abstract class VehicleRequest extends FormRequest
{
    /**
     * Batas ukuran foto (KB). Browser mengompres foto sampai di bawah batas ini sebelum dikirim.
     */
    public const MAX_PHOTO_KB = 1024;

    abstract protected function uniquePlateRule(): Unique;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plate_number' => strtoupper(preg_replace('/\s+/', ' ', trim((string) $this->input('plate_number')))),
            'is_available' => $this->boolean('is_available'),
        ]);
    }

    /**
     * Data kendaraan tervalidasi tanpa field foto, yang ditangani terpisah.
     *
     * @return array<string, mixed>
     */
    public function vehicleAttributes(): array
    {
        return $this->safe()->except(['photo', 'remove_photo']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'plate_number' => ['required', 'string', 'max:20', $this->uniquePlateRule()],
            'type' => ['required', Rule::enum(VehicleType::class)],
            'year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'color' => ['required', 'string', 'max:50'],
            'price_per_day' => ['required', 'integer', 'min:1', 'max:100000000'],
            'price_per_hour' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_PHOTO_KB, 'dimensions:max_width=8000,max_height=8000'],
            'is_available' => ['boolean'],
            'drive_type' => ['required', Rule::enum(VehicleDriveType::class)],
            'energy_capacity' => ['required', 'numeric', 'gt:0', 'max:9999.9', 'decimal:0,1'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plate_number.unique' => 'Plat nomor sudah terdaftar.',
            'drive_type.required' => 'Tipe kendaraan wajib dipilih.',
            'energy_capacity.required' => 'Kapasitas energi wajib diisi.',
            'energy_capacity.gt' => 'Kapasitas energi harus lebih dari 0.',
            'energy_capacity.decimal' => 'Kapasitas energi maksimal satu angka di belakang koma.',
            'photo.max' => 'Ukuran foto maksimal 1 MB.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.dimensions' => 'Dimensi foto maksimal 8000 x 8000 piksel.',
        ];
    }
}
