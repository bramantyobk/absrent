<?php

namespace App\View\Components\Customer;

use App\Models\CompanyProfile;
use App\Services\WhatsAppLink;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Footer extends Component
{
    public CompanyProfile $company;

    /**
     * @var list<array{day: string, label: string, is_open: bool, open_at: ?string, close_at: ?string}>
     */
    public array $hours;

    /**
     * Hari berurutan dengan jam sama digabung agar footer ringkas.
     *
     * @var list<array{label: string, to: string, hours: string}>
     */
    public array $hourGroups = [];

    public ?string $supportUrl;

    public function __construct(WhatsAppLink $whatsApp)
    {
        $this->company = CompanyProfile::current();
        $this->hours = $this->company->operatingHours();
        foreach ($this->hours as $day) {
            $hours = $day['is_open'] ? "{$day['open_at']} - {$day['close_at']}" : 'Libur';
            $last = array_key_last($this->hourGroups);

            if ($last !== null && $this->hourGroups[$last]['hours'] === $hours) {
                $this->hourGroups[$last]['to'] = $day['label'];
            } else {
                $this->hourGroups[] = ['label' => $day['label'], 'to' => $day['label'], 'hours' => $hours];
            }
        }

        $this->supportUrl = $whatsApp->toSupport($this->company, 'Halo, saya ingin bertanya tentang layanan sewa kendaraan.');
    }

    public function render(): View|Closure|string
    {
        return view('components.customer.footer');
    }
}
