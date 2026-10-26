<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Order;

class WhatsAppLink
{
    public function __construct(private WhatsAppTemplate $template) {}

    /**
     * Tautan ke nomor WhatsApp support perusahaan, atau null bila nomor support belum diisi.
     */
    public function toSupport(CompanyProfile $company, string $message): ?string
    {
        return $company->support_whatsapp === '' ? null : $this->make($company->support_whatsapp, $message);
    }

    /**
     * Tautan konfirmasi pembayaran ke operator yang ditetapkan rotator pada pesanan, atau ke nomor support bila
     * pesanan tidak punya operator. Pesan dibuat dari template perusahaan. Null bila tidak ada nomor tujuan.
     */
    public function toOperator(Order $order, CompanyProfile $company): ?string
    {
        $number = $order->assignedOperator?->phone ?: $company->support_whatsapp;

        if (blank($number)) {
            return null;
        }

        return $this->make($number, $this->template->render($this->template->for($company), $this->template->valuesFor($order)));
    }

    /**
     * Tautan pengajuan pembatalan ke operator pesanan, atau ke nomor support bila tidak ada operator. Null bila tidak
     * ada nomor tujuan.
     */
    public function toCancellation(Order $order, CompanyProfile $company, string $reason): ?string
    {
        $number = $order->assignedOperator?->phone ?: $company->support_whatsapp;

        if (blank($number)) {
            return null;
        }

        return $this->make($number, "Halo, saya ingin mengajukan pembatalan pesanan {$order->code}.\nAlasan: {$reason}");
    }

    /**
     * Tautan `https://wa.me/<nomor>?text=<pesan>`. Nomor berformat 62... tanpa tanda plus, pesan di-encode URL.
     */
    public function make(string $number, string $message): string
    {
        return 'https://wa.me/'.preg_replace('/\D+/', '', $number).'?text='.rawurlencode($message);
    }
}
