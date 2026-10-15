<?php

namespace App\Services;

use App\Models\CompanyProfile;

class WhatsAppLink
{
    /**
     * Tautan ke nomor WhatsApp support perusahaan, atau null bila nomor support belum diisi.
     */
    public function toSupport(CompanyProfile $company, string $message): ?string
    {
        return $company->support_whatsapp === '' ? null : $this->make($company->support_whatsapp, $message);
    }

    /**
     * Tautan `https://wa.me/<nomor>?text=<pesan>`. Nomor berformat 62... tanpa tanda plus, pesan di-encode URL.
     */
    public function make(string $number, string $message): string
    {
        return 'https://wa.me/'.preg_replace('/\D+/', '', $number).'?text='.rawurlencode($message);
    }
}
