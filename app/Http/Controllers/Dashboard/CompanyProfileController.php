<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Company\UpdateCompanyProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use App\Services\WhatsAppTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function edit(WhatsAppTemplate $whatsAppTemplate): View
    {
        $company = CompanyProfile::current();

        Gate::authorize('view', $company);

        return view('dashboard.company.edit', [
            'company' => $company,
            'hours' => $company->operatingHours(),
            'bankAccounts' => $company->bankAccounts()->ordered()->get(),
            'whatsAppTemplate' => $whatsAppTemplate->for($company),
            'defaultWhatsAppTemplate' => WhatsAppTemplate::DEFAULT_TEMPLATE,
            'whatsAppPlaceholders' => WhatsAppTemplate::PLACEHOLDERS,
            'whatsAppSample' => $whatsAppTemplate->sampleValues(),
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
