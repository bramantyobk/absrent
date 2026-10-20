<?php

namespace App\Http\Requests;

use App\Models\CompanyProfile;
use App\Services\TermsTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTermsTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', CompanyProfile::current());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(TermsTemplate $template): array
    {
        return [
            'terms_template' => [
                'nullable',
                'string',
                'max:'.TermsTemplate::MAX_LENGTH,
                function (string $attribute, mixed $value, Closure $fail) use ($template): void {
                    $unknown = $template->unknownPlaceholders((string) $value);

                    if ($unknown !== []) {
                        $fail('Placeholder tidak dikenal: '.implode(', ', $unknown).'.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_template.max' => 'Template maksimal '.number_format(TermsTemplate::MAX_LENGTH, 0, ',', '.').' karakter.',
        ];
    }
}
