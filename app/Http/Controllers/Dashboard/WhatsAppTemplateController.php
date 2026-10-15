<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Company\UpdateWhatsAppTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWhatsAppTemplateRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;

class WhatsAppTemplateController extends Controller
{
    public function update(UpdateWhatsAppTemplateRequest $request, UpdateWhatsAppTemplate $updateWhatsAppTemplate): RedirectResponse
    {
        $updateWhatsAppTemplate->handle(CompanyProfile::current(), $request->validated('whatsapp_confirmation_template'));

        return redirect()
            ->route('dashboard.company.edit')
            ->with('status', 'Template pesan WhatsApp berhasil diperbarui.');
    }
}
