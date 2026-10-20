<?php

namespace App\Http\Resources;

use App\Models\CompanyProfile;
use App\Services\TermsTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyProfile
 */
class TermsTemplateResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'terms_template' => app(TermsTemplate::class)->for($this->resource),
            'uses_default_template' => blank($this->terms_template),
            'placeholders' => TermsTemplate::PLACEHOLDERS,
            'updated_at' => $this->updated_at,
        ];
    }
}
