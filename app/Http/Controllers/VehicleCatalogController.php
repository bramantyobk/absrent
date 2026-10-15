<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use App\Models\CompanyProfile;
use App\Models\Vehicle;
use App\Services\CheckoutCart;
use App\Services\RentalQuote;
use App\Services\WhatsAppLink;
use App\VehicleDriveType;
use App\VehicleType;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VehicleCatalogController extends Controller
{
    public function index(CatalogFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('vehicles.index', [
            'vehicles' => Vehicle::query()
                ->listedInCatalog()
                ->catalogFilter($filters)
                ->orderBy('brand')
                ->orderBy('model')
                ->orderBy('id')
                ->paginate($filters['per_page'] ?? 12)
                ->withQueryString(),
            'categories' => VehicleType::cases(),
            'driveTypes' => VehicleDriveType::cases(),
            'dayChoices' => range(1, config('rental.max_rental_days')),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Vehicle $vehicle, WhatsAppLink $whatsApp, CheckoutCart $cart, RentalQuote $rentalQuote): View
    {
        abort_unless($vehicle->is_available, 404);

        $vehicle->loadEquipment();

        $isCustomer = $request->user() !== null && ! $request->user()->isStaff();
        $cartPeriod = $isCustomer ? $cart->period() : null;
        $inCart = $isCustomer && in_array($vehicle->getKey(), $cart->vehicleIds(), true);
        $isLocked = $cartPeriod !== null;

        $startAt = $isLocked
            ? RentalQuote::parse($cartPeriod['start_at'])->format('Y-m-d\TH:i')
            : $this->inputValue($request->old('start_at', $request->query('start_at')));
        $days = $isLocked ? $cartPeriod['days'] : (int) $request->old('days', $request->query('days', 0));

        [$options, $notice] = $isCustomer && ! $inCart && $startAt !== ''
            ? $this->returnOptions($rentalQuote, $vehicle, $startAt)
            : [collect(), null];

        return view('vehicles.show', [
            'vehicle' => $vehicle,
            'isCustomer' => $isCustomer,
            'isLocked' => $isLocked,
            'inCart' => $inCart,
            'canAddMore' => $cart->canAddMore(),
            'maxVehicles' => config('rental.max_vehicles_per_order'),
            'startAt' => $startAt,
            'startLabel' => $startAt === '' ? '' : RentalQuote::parse($startAt)->format('d/m/Y H:i'),
            'days' => $days,
            'options' => $options,
            'optionsNotice' => $notice,
            'timezoneLabel' => config('rental.timezone_label'),
            'supportUrl' => $whatsApp->toSupport(CompanyProfile::current(), "Halo, saya ingin bertanya tentang {$vehicle->brand} {$vehicle->model}."),
        ]);
    }

    /**
     * Opsi durasi untuk waktu mulai tertentu, atau pesan mengapa tidak ada.
     *
     * @return array{0: Collection<int, array{days: int, end_at: Carbon, end_label: string, subtotal: int}>, 1: ?string}
     */
    private function returnOptions(RentalQuote $rentalQuote, Vehicle $vehicle, string $startAt): array
    {
        try {
            $options = $rentalQuote->options($vehicle, $startAt);
        } catch (ValidationException $exception) {
            return [collect(), collect($exception->errors())->flatten()->first()];
        }

        return [$options, $options->isEmpty() ? 'Tidak tersedia pada waktu mulai ini.' : null];
    }

    /**
     * Nilai awal input datetime-local dari query string atau input lama, atau kosong bila tidak terbaca.
     */
    private function inputValue(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return RentalQuote::parse($value)->format('Y-m-d\TH:i');
        } catch (InvalidFormatException) {
            return '';
        }
    }
}
