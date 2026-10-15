<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Cache;

/**
 * Satu-satunya pintu ke data perusahaan (satu baris). Dibuat sekali per request (scoped) agar footer dan controller
 * tidak query dua kali, dan baris awal dibuat di bawah kunci agar dua permintaan bersamaan tidak membuat dua baris.
 */
class CompanyProfileResolver
{
    private ?CompanyProfile $profile = null;

    public function get(): CompanyProfile
    {
        return $this->profile ??= $this->first() ?? $this->createDefault();
    }

    private function first(): ?CompanyProfile
    {
        return CompanyProfile::query()->orderBy('id')->first();
    }

    private function createDefault(): CompanyProfile
    {
        return Cache::lock('company-profile:create', 10)->block(5, fn (): CompanyProfile => $this->first() ?? CompanyProfile::create([
            'name' => config('app.name'),
            'address' => '',
            'support_whatsapp' => '',
            'operating_hours' => CompanyProfile::defaultOperatingHours(),
        ]));
    }
}
