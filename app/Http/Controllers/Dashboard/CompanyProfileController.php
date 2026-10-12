<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Company\UpdateCompanyProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function edit(): View
    {
        $company = CompanyProfile::current();

        Gate::authorize('view', $company);

        return view('dashboard.company.edit', [
            'company' => $company,
            'hours' => $company->operatingHours(),
        ]);
    }

    public function update(UpdateCompanyProfileRequest $request, UpdateCompanyProfile $updateCompanyProfile): RedirectResponse
    {
        $updateCompanyProfile->handle(CompanyProfile::current(), $request->companyAttributes());

        return redirect()
            ->route('dashboard.company.edit')
            ->with('status', 'Data perusahaan berhasil diperbarui.');
    }
}
