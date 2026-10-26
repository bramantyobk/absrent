<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Vehicle;
use App\Services\WhatsAppLink;
use App\VehicleType;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(WhatsAppLink $whatsApp): View
    {
        return view('home', [
            'vehicles' => Vehicle::query()->listedInCatalog()->latest('id')->limit(6)->get(),
            'categories' => VehicleType::cases(),
            'defaultStart' => now()->addDay()->format('Y-m-d').'T09:00',
            'dayChoices' => range(1, config('rental.max_rental_days')),
            'supportUrl' => $whatsApp->toSupport(CompanyProfile::current(), 'Halo, saya ingin bertanya tentang layanan sewa kendaraan.'),
        ]);
    }
}
