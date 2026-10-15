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

    public ?string $supportUrl;

    public function __construct(WhatsAppLink $whatsApp)
    {
        $this->company = CompanyProfile::current();
        $this->hours = $this->company->operatingHours();
        $this->supportUrl = $whatsApp->toSupport($this->company, 'Halo, saya ingin bertanya tentang layanan sewa kendaraan.');
    }

    public function render(): View|Closure|string
    {
        return view('components.customer.footer');
    }
}
