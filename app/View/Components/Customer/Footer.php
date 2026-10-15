<?php

namespace App\View\Components\Customer;

use App\Models\CompanyProfile;
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

    public function __construct()
    {
        $this->company = CompanyProfile::current();
        $this->hours = $this->company->operatingHours();
        $this->supportUrl = $this->company->supportWhatsAppUrl('Halo, saya ingin bertanya tentang layanan sewa kendaraan.');
    }

    public function render(): View|Closure|string
    {
        return view('components.customer.footer');
    }
}
