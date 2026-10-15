<?php

namespace App\Http\Requests;

use App\Models\CompanyProfile;
use App\Services\WhatsAppTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateWhatsAppTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', CompanyProfile::current());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(WhatsAppTemplate $template): array
    {
        return [
            'whatsapp_confirmation_template' => [
                'nullable',
                'string',
                'max:'.WhatsAppTemplate::MAX_LENGTH,
                function (string $attribute, mixed $value, Closure $fail) use ($template): void {
                    $unknown = $template->unknownPlaceholders((string) $value);

                    if ($unknown !== []) {
                        $fail('Placeholder tidak dikenal: '.implode(', ', $unknown).'.');
                    }

                    if (! str_contains((string) $value, WhatsAppTemplate::REQUIRED_PLACEHOLDER)) {
                        $fail('Template wajib memuat '.WhatsAppTemplate::REQUIRED_PLACEHOLDER.'.');
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
            'whatsapp_confirmation_template.max' => 'Template maksimal '.WhatsAppTemplate::MAX_LENGTH.' karakter.',
        ];
    }
}
