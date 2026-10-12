<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Actions\Vehicles\CancelVehicleSchedule;
use App\Actions\Vehicles\CreateVehicleSchedule;
use App\Actions\Vehicles\UpdateVehicleSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleScheduleRequest;
use App\Http\Requests\UpdateVehicleScheduleRequest;
use App\Http\Resources\VehicleScheduleResource;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VehicleScheduleController extends Controller
{
    /**
     * Daftar jadwal servis kendaraan
     *
     * Diurutkan dari yang terbaru. Admin dan operator.
     */
    public function index(Vehicle $vehicle): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', VehicleSchedule::class);

        return VehicleScheduleResource::collection(
            $vehicle->schedules()->latest('start_at')->paginate(15)
        );
    }

    /**
     * Tambah jadwal servis
     *
     * Ditolak dengan 422 bila overlap dengan pesanan aktif (termasuk Pending dan jeda pengembalian) atau jadwal servis lain.
     */
    public function store(StoreVehicleScheduleRequest $request, Vehicle $vehicle, CreateVehicleSchedule $createSchedule): VehicleScheduleResource
    {
        /** @status 201 */
        return new VehicleScheduleResource($createSchedule->handle($vehicle, $request->validated(), $request->user()));
    }

    /**
     * Ubah jadwal servis
     *
     * Jadwal yang sedang berjalan hanya dapat dipersingkat atau diperpanjang. Jadwal yang sudah lewat tidak dapat diubah.
     */
    public function update(UpdateVehicleScheduleRequest $request, Vehicle $vehicle, VehicleSchedule $schedule, UpdateVehicleSchedule $updateSchedule): VehicleScheduleResource
    {
        return new VehicleScheduleResource($updateSchedule->handle($schedule, $request->validated()));
    }

    /**
     * Batalkan jadwal servis
     *
     * Hanya jadwal yang belum mulai. Ditolak dengan 422 untuk jadwal yang sedang berjalan atau sudah lewat.
     */
    public function destroy(Vehicle $vehicle, VehicleSchedule $schedule, CancelVehicleSchedule $cancelSchedule): Response
    {
        Gate::authorize('delete', $schedule);

        $cancelSchedule->handle($schedule);

        /** @status 204 */
        return response()->noContent();
    }
}
