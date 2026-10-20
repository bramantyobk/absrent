<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Company\UpdateTermsTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTermsTemplateRequest;
use App\Http\Resources\TermsTemplateResource;
use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Gate;

class TermsController extends Controller
{
    /**
     * Template syarat dan ketentuan
     *
     * Template yang berlaku (bawaan bila belum pernah diubah) beserta daftar placeholder yang tersedia. Khusus admin.
     */
    public function show(): TermsTemplateResource
    {
        $company = CompanyProfile::current();

        Gate::authorize('view', $company);

        return new TermsTemplateResource($company);
    }

    /**
     * Ubah template syarat dan ketentuan
     *
     * Isi Surat Perjanjian Sewa Menyewa. Baris berawalan `# ` menjadi judul dan baris kosong memisahkan paragraf. Placeholder:
     * `{name}`, `{phone}`, `{email}`, `{company_name}`, `{company_address}`, `{company_phone}`, `{vehicles}`, `{start_at}`,
     * `{end_at}`, `{days}`, `{total_price}`, `{today}`, maksimal 20.000 karakter. Kirim `null` untuk kembali ke template bawaan.
     * Khusus admin.
     */
    public function update(UpdateTermsTemplateRequest $request, UpdateTermsTemplate $updateTermsTemplate): TermsTemplateResource
    {
        return new TermsTemplateResource(
            $updateTermsTemplate->handle(CompanyProfile::current(), $request->validated('terms_template'))
        );
    }
}
