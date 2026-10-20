<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Settings\UpdateRentalSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRentalSettingsRequest;
use App\Models\RentalSetting;
use App\Services\RentalSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RentalSettingController extends Controller
{
    public function edit(RentalSettings $rentalSettings): View
    {
        Gate::authorize('view', RentalSetting::current());

        return view('dashboard.settings.rental', [
            'fields' => RentalSettings::FIELDS,
            'values' => $rentalSettings->current(),
            'defaults' => $rentalSettings->defaults(),
        ]);
    }

    public function update(UpdateRentalSettingsRequest $request, UpdateRentalSettings $updateRentalSettings): RedirectResponse
    {
        $updateRentalSettings->handle(RentalSetting::current(), $request->settings());

        return redirect()
            ->route('dashboard.settings.rental.edit')
            ->with('status', 'Pengaturan sewa berhasil diperbarui.');
    }
}
