<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePhotoRequest extends FormRequest
{
    protected $errorBag = 'photo';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.VehicleRequest::MAX_PHOTO_KB, 'dimensions:max_width=8000,max_height=8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Pilih foto terlebih dahulu.',
            'photo.max' => 'Ukuran foto maksimal 1 MB.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.dimensions' => 'Dimensi foto maksimal 8000 x 8000 piksel.',
        ];
    }
}
