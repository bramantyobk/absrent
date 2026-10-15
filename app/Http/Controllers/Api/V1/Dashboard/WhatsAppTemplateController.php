<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Company\UpdateWhatsAppTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWhatsAppTemplateRequest;
use App\Http\Resources\CompanyProfileResource;
use App\Models\CompanyProfile;

class WhatsAppTemplateController extends Controller
{
    /**
     * Ubah template pesan WhatsApp
     *
     * Template pesan konfirmasi pembayaran yang dikirim pelanggan ke operator. Placeholder: `{name}`, `{order_code}`
     * (wajib), `{bank_name}`, `{account_number}`, `{account_holder}`, `{total_price}`, maksimal 1000 karakter.
     * Kirim `null` untuk kembali ke template bawaan. Khusus admin.
     */
    public function update(UpdateWhatsAppTemplateRequest $request, UpdateWhatsAppTemplate $updateWhatsAppTemplate): CompanyProfileResource
    {
        return new CompanyProfileResource(
            $updateWhatsAppTemplate->handle(CompanyProfile::current(), $request->validated('whatsapp_confirmation_template'))
        );
    }
}
