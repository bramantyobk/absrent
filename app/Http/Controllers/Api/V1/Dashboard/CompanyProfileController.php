<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Company\UpdateCompanyProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Http\Resources\CompanyProfileResource;
use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Gate;

class CompanyProfileController extends Controller
{
    /**
     * Data perusahaan
     *
     * Satu baris data: nama, alamat, nomor WhatsApp support, jam layanan kantor (Senin sampai Minggu), dan daftar rekening bank. Khusus admin.
     */
    public function show(): CompanyProfileResource
    {
        $company = CompanyProfile::current();

        Gate::authorize('view', $company);

        return new CompanyProfileResource($company->load(['bankAccounts' => fn ($accounts) => $accounts->ordered()]));
    }

    /**
     * Ubah data perusahaan
     *
     * Kirim `operating_hours` sebagai tujuh entri (`day`, `is_open`, `open_at`, `close_at` berformat `HH:MM`). Hari libur tidak memerlukan jam.
     * Jam layanan hanya informasi untuk booking langsung di kantor dan tidak membatasi pemesanan online.
     */
    public function update(UpdateCompanyProfileRequest $request, UpdateCompanyProfile $updateCompanyProfile): CompanyProfileResource
    {
        return new CompanyProfileResource(
            $updateCompanyProfile->handle(CompanyProfile::current(), $request->companyAttributes())
        );
    }
}
