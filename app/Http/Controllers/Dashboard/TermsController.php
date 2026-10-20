<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Company\UpdateTermsTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTermsTemplateRequest;
use App\Models\CompanyProfile;
use App\Services\TermsTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TermsController extends Controller
{
    public function edit(TermsTemplate $termsTemplate): View
    {
        $company = CompanyProfile::current();

        Gate::authorize('view', $company);

        return view('dashboard.terms.edit', [
            'template' => $termsTemplate->for($company),
            'defaultTemplate' => TermsTemplate::DEFAULT_TEMPLATE,
            'placeholders' => TermsTemplate::PLACEHOLDERS,
            'sample' => $termsTemplate->sampleValues(),
            'maxLength' => TermsTemplate::MAX_LENGTH,
        ]);
    }

    public function update(UpdateTermsTemplateRequest $request, UpdateTermsTemplate $updateTermsTemplate): RedirectResponse
    {
        $updateTermsTemplate->handle(CompanyProfile::current(), $request->validated('terms_template'));

        return redirect()
            ->route('dashboard.terms.edit')
            ->with('status', 'Syarat dan ketentuan berhasil diperbarui.');
    }
}
