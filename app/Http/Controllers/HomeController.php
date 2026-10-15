<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\VehicleType;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'vehicles' => Vehicle::query()->listedInCatalog()->latest('id')->limit(6)->get(),
            'categories' => VehicleType::cases(),
        ]);
    }
}
