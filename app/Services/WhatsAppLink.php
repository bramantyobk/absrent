<?php

namespace App\Services;

class WhatsAppLink
{
    /**
     * Tautan `https://wa.me/<nomor>?text=<pesan>`. Nomor berformat 62... tanpa tanda plus, pesan di-encode URL.
     */
    public function make(string $number, string $message): string
    {
        return 'https://wa.me/'.preg_replace('/\D+/', '', $number).'?text='.rawurlencode($message);
    }
}
