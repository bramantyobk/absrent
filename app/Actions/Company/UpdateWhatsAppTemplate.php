<?php

namespace App\Actions\Company;

use App\Models\CompanyProfile;

class UpdateWhatsAppTemplate
{
    /**
     * Template kosong berarti memakai template bawaan.
     */
    public function handle(CompanyProfile $companyProfile, ?string $template): CompanyProfile
    {
        $template = $template === null ? null : str_replace("\r\n", "\n", trim($template));

        $companyProfile->update(['whatsapp_confirmation_template' => $template === '' ? null : $template]);

        return $companyProfile;
    }
}
