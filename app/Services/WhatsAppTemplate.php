<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Order;

/**
 * Template pesan WhatsApp konfirmasi pembayaran: teks bawaan, placeholder yang dikenal, dan pengisiannya.
 */
class WhatsAppTemplate
{
    public const DEFAULT_TEMPLATE = "Halo, saya {name} mau konfirmasi pembayaran:\nNomor Pesanan: {order_code}\nBank Transfer: {bank_name}, {account_number}\nNominal Transfer: {total_price}";

    public const MAX_LENGTH = 1000;

    public const REQUIRED_PLACEHOLDER = '{order_code}';

    /**
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        '{name}' => 'Nama pelanggan',
        '{order_code}' => 'Kode pesanan',
        '{bank_name}' => 'Nama bank tujuan',
        '{account_number}' => 'Nomor rekening tujuan',
        '{account_holder}' => 'Atas nama rekening',
        '{total_price}' => 'Total pembayaran',
    ];

    /**
     * Template yang berlaku: isian admin, atau bawaan bila kosong.
     */
    public function for(CompanyProfile $company): string
    {
        $template = trim((string) $company->whatsapp_confirmation_template);

        return $template === '' ? self::DEFAULT_TEMPLATE : $template;
    }

    /**
     * Placeholder pada template yang tidak dikenal, mis. `{nama}`.
     *
     * @return list<string>
     */
    public function unknownPlaceholders(string $template): array
    {
        preg_match_all('/\{[^{}\s]*\}/', $template, $matches);

        return array_values(array_unique(array_diff($matches[0], array_keys(self::PLACEHOLDERS))));
    }

    /**
     * @param  array<string, string>  $values  placeholder (dengan kurung kurawal) => nilai
     */
    public function render(string $template, array $values): string
    {
        return strtr(str_replace("\r\n", "\n", $template), $values);
    }

    /**
     * Nilai placeholder dari pesanan dengan rekening tujuan yang sudah dipilih pelanggan.
     *
     * @return array<string, string>
     */
    public function valuesFor(Order $order): array
    {
        $bank = $order->transaction?->bankAccount;

        return [
            '{name}' => $order->user->name,
            '{order_code}' => $order->code,
            '{bank_name}' => $bank?->bank_name ?? '-',
            '{account_number}' => $bank?->account_number ?? '-',
            '{account_holder}' => $bank?->account_holder ?? '-',
            '{total_price}' => $order->formattedTotalPrice(),
        ];
    }

    /**
     * Data contoh untuk pratinjau di dashboard.
     *
     * @return array<string, string>
     */
    public function sampleValues(): array
    {
        return [
            '{name}' => 'Budi Santoso',
            '{order_code}' => 'ABS-7K3Q9X2M',
            '{bank_name}' => 'BCA',
            '{account_number}' => '1234567890',
            '{account_holder}' => 'PT ABSRENT',
            '{total_price}' => 'Rp450.000',
        ];
    }
}
