<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonInterface;

/**
 * Template Syarat dan Ketentuan (isi Surat Perjanjian Sewa Menyewa): teks bawaan, placeholder yang dikenal, pengisian nilai,
 * dan pemecahan teks menjadi paragraf dan judul untuk ditampilkan. Daftar placeholder dan pengisiannya sengaja dipisahkan dari
 * cara tampilnya agar dapat dipakai ulang bila kelak dokumen dihasilkan dalam format lain (mis. .docx).
 */
class TermsTemplate
{
    public const DEFAULT_TEMPLATE = <<<'TEXT'
# SURAT PERJANJIAN SEWA MENYEWA KENDARAAN

Pada hari ini, {today}, yang bertanda tangan di bawah ini:

Pihak Pertama: {company_name}, beralamat di {company_address}, WhatsApp {company_phone}.
Pihak Kedua: {name}, WhatsApp {phone}, email {email}.

# Pasal 1. Objek Sewa

Pihak Pertama menyewakan kepada Pihak Kedua kendaraan berikut:
{vehicles}

# Pasal 2. Jangka Waktu

Sewa dimulai pada {start_at} dan berakhir pada {end_at} ({days} hari). Kendaraan wajib dikembalikan pada waktu yang ditentukan.

# Pasal 3. Biaya Sewa

Total biaya sewa adalah {total_price} dan dibayar melalui transfer ke rekening perusahaan sebelum batas waktu pembayaran.

# Pasal 4. Kewajiban Penyewa

Pihak Kedua wajib menjaga kendaraan, memakainya sesuai peruntukan, mematuhi peraturan lalu lintas, dan bertanggung jawab atas kerusakan atau kehilangan selama masa sewa.

# Pasal 5. Penutup

Dengan menyetujui syarat dan ketentuan ini, Pihak Kedua menyatakan telah membaca dan memahami seluruh isi perjanjian.
TEXT;

    public const MAX_LENGTH = 20000;

    /**
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        '{name}' => 'Nama pelanggan',
        '{phone}' => 'Nomor WhatsApp pelanggan',
        '{email}' => 'Email pelanggan',
        '{company_name}' => 'Nama perusahaan',
        '{company_address}' => 'Alamat perusahaan',
        '{company_phone}' => 'Nomor WhatsApp perusahaan',
        '{vehicles}' => 'Daftar kendaraan beserta plat nomor',
        '{start_at}' => 'Waktu mulai sewa',
        '{end_at}' => 'Waktu kendaraan kembali',
        '{days}' => 'Jumlah hari sewa',
        '{total_price}' => 'Total biaya sewa',
        '{today}' => 'Tanggal hari ini',
    ];

    public function __construct(private RentalQuote $rentalQuote) {}

    /**
     * Template yang berlaku: isian admin, atau bawaan bila kosong.
     */
    public function for(CompanyProfile $company): string
    {
        $template = trim((string) $company->terms_template);

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
     * Nilai placeholder dari calon pesanan (belum ada kode pesanan).
     *
     * @param  iterable<Vehicle>  $vehicles
     * @return array<string, string>
     */
    public function valuesFor(CompanyProfile $company, User $customer, iterable $vehicles, CarbonInterface $startAt, int $days, int $totalPrice): array
    {
        $lines = [];

        foreach ($vehicles as $vehicle) {
            $lines[] = (count($lines) + 1).". {$vehicle->brand} {$vehicle->model} ({$vehicle->plate_number})";
        }

        return [
            '{name}' => $customer->name,
            '{phone}' => (string) $customer->phone,
            '{email}' => $customer->email,
            '{company_name}' => (string) $company->name,
            '{company_address}' => (string) $company->address,
            '{company_phone}' => (string) $company->support_whatsapp,
            '{vehicles}' => implode("\n", $lines),
            '{start_at}' => $this->rentalQuote->label($startAt),
            '{end_at}' => $this->rentalQuote->label($startAt->copy()->addDays($days)),
            '{days}' => (string) $days,
            '{total_price}' => 'Rp'.number_format($totalPrice, 0, ',', '.'),
            '{today}' => $this->rentalQuote->label(now()),
        ];
    }

    /**
     * Teks S&K yang berlaku untuk calon pesanan, sudah terisi, beserta bloknya untuk ditampilkan.
     *
     * @param  iterable<Vehicle>  $vehicles
     * @return array{text: string, blocks: list<array{type: 'heading'|'paragraph', text: string}>}
     */
    public function forOrder(CompanyProfile $company, User $customer, iterable $vehicles, CarbonInterface $startAt, int $days, int $totalPrice): array
    {
        $text = $this->render($this->for($company), $this->valuesFor($company, $customer, $vehicles, $startAt, $days, $totalPrice));

        return ['text' => $text, 'blocks' => $this->blocks($text)];
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
            '{phone}' => '6281234567890',
            '{email}' => 'budi@example.com',
            '{company_name}' => 'ABSRENT',
            '{company_address}' => 'Jl. Contoh No. 1, Denpasar',
            '{company_phone}' => '6281200000000',
            '{vehicles}' => "1. Honda Beat (DK 1234 AB)\n2. Toyota Avanza (DK 5678 CD)",
            '{start_at}' => 'Senin, 20/10/2026 09:00 WIB',
            '{end_at}' => 'Rabu, 22/10/2026 09:00 WIB',
            '{days}' => '2',
            '{total_price}' => 'Rp450.000',
            '{today}' => 'Senin, 19/10/2026 10:00 WIB',
        ];
    }

    /**
     * @param  array<string, string>  $values  placeholder (dengan kurung kurawal) => nilai
     */
    public function render(string $template, array $values): string
    {
        return strtr(str_replace("\r\n", "\n", $template), $values);
    }

    /**
     * Memecah teks yang sudah terisi menjadi blok untuk ditampilkan: baris berawalan `# ` menjadi judul, baris kosong memisahkan
     * paragraf. Keluarannya teks polos (di-escape oleh Blade), tanpa HTML dari admin.
     *
     * @return list<array{type: 'heading'|'paragraph', text: string}>
     */
    public function blocks(string $rendered): array
    {
        $blocks = [];
        $paragraph = [];

        $flush = function () use (&$blocks, &$paragraph): void {
            if ($paragraph !== []) {
                $blocks[] = ['type' => 'paragraph', 'text' => implode("\n", $paragraph)];
                $paragraph = [];
            }
        };

        foreach (explode("\n", str_replace("\r\n", "\n", $rendered)) as $line) {
            $line = rtrim($line);

            if (str_starts_with($line, '# ')) {
                $flush();
                $blocks[] = ['type' => 'heading', 'text' => trim(substr($line, 2))];
            } elseif (trim($line) === '') {
                $flush();
            } else {
                $paragraph[] = $line;
            }
        }

        $flush();

        return $blocks;
    }
}
