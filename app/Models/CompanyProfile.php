<?php

namespace App\Models;

use App\Services\WhatsAppLink;
use App\Weekday;
use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address', 'support_whatsapp', 'operating_hours'])]
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operating_hours' => 'array',
        ];
    }

    /**
     * Data perusahaan hanya satu baris. Baris kosong dibuat bila belum ada (mis. instalasi baru).
     */
    public static function current(): self
    {
        return self::query()->first() ?? self::create([
            'name' => config('app.name'),
            'address' => '',
            'support_whatsapp' => '',
            'operating_hours' => self::defaultOperatingHours(),
        ]);
    }

    /**
     * Senin sampai Sabtu buka 08:00-17:00, Minggu libur.
     *
     * @return list<array{day: string, is_open: bool, open_at: ?string, close_at: ?string}>
     */
    public static function defaultOperatingHours(): array
    {
        return array_map(fn (Weekday $day): array => [
            'day' => $day->value,
            'is_open' => $day !== Weekday::Sunday,
            'open_at' => $day !== Weekday::Sunday ? '08:00' : null,
            'close_at' => $day !== Weekday::Sunday ? '17:00' : null,
        ], Weekday::cases());
    }

    /**
     * Jam layanan kantor untuk tujuh hari berurutan Senin sampai Minggu. Hari yang belum diisi memakai nilai bawaan.
     *
     * @return list<array{day: string, label: string, is_open: bool, open_at: ?string, close_at: ?string}>
     */
    public function operatingHours(): array
    {
        $saved = collect($this->operating_hours ?? [])->keyBy('day');
        $defaults = collect(self::defaultOperatingHours())->keyBy('day');

        return array_map(function (Weekday $day) use ($saved, $defaults): array {
            $hours = $saved->get($day->value) ?? $defaults->get($day->value);

            return [
                'day' => $day->value,
                'label' => $day->label(),
                'is_open' => (bool) $hours['is_open'],
                'open_at' => $hours['is_open'] ? $hours['open_at'] : null,
                'close_at' => $hours['is_open'] ? $hours['close_at'] : null,
            ];
        }, Weekday::cases());
    }

    /**
     * Tautan WhatsApp ke nomor support dengan pesan awal, atau null bila nomor support belum diisi.
     */
    public function supportWhatsAppUrl(string $message): ?string
    {
        return $this->support_whatsapp === '' ? null : app(WhatsAppLink::class)->make($this->support_whatsapp, $message);
    }

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
