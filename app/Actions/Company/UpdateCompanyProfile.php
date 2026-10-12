<?php

namespace App\Actions\Company;

use App\Models\CompanyProfile;

class UpdateCompanyProfile
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(CompanyProfile $companyProfile, array $data): CompanyProfile
    {
        $companyProfile->update($data);

        return $companyProfile;
    }
}
