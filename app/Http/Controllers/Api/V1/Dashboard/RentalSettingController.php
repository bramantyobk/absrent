<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Settings\UpdateRentalSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRentalSettingsRequest;
use App\Http\Resources\RentalSettingsResource;
use App\Models\RentalSetting;
use Illuminate\Support\Facades\Gate;

class RentalSettingController extends Controller
{
    /**
     * Pengaturan sewa
     *
     * Nilai yang berlaku, nilai bawaan, dan batas tiap pengaturan. Khusus admin.
     */
    public function show(): RentalSettingsResource
    {
        Gate::authorize('view', RentalSetting::current());

        return new RentalSettingsResource(RentalSetting::current());
    }

    /**
     * Ubah pengaturan sewa
     *
     * Semua pengaturan wajib dikirim (bilangan bulat dalam batas masing-masing): `return_buffer_hours`, `min_free_window_hours`,
     * `min_rental_hours`, `max_rental_days`, `pending_expiry_hours`, `confirm_validation_window_hours`,
     * `verification_warning_hours`, `vehicle_change_admin_threshold`, `max_pending_orders`, dan `max_vehicles_per_order`.
     * Berlaku untuk pesanan berikutnya. Khusus admin.
     */
    public function update(UpdateRentalSettingsRequest $request, UpdateRentalSettings $updateRentalSettings): RentalSettingsResource
    {
        return new RentalSettingsResource($updateRentalSettings->handle(RentalSetting::current(), $request->settings()));
    }
}
