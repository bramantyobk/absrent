<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class EnsureScheduleIsFree
{
    public const ERROR_BAG = 'schedule';

    /**
     * Menolak rentang servis yang overlap dengan pesanan aktif (termasuk Pending dan jeda pengembalian)
     * atau jadwal servis lain. Pesan menyebut kode dan tanggal pesanan yang bentrok.
     *
     * @throws ValidationException
     */
    public function handle(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end, ?int $ignoreScheduleId = null): void
    {
        $messages = [];

        foreach ($vehicle->conflictingOrderItems($start, $end) as $item) {
            $messages[] = "Bentrok dengan pesanan {$item->order->code} ({$item->start_at->format('d/m/Y H:i')} - {$item->end_at->format('d/m/Y H:i')}, ditambah jeda ".config('rental.return_buffer_hours').' jam).';
        }

        foreach ($vehicle->conflictingSchedules($start, $end, $ignoreScheduleId) as $schedule) {
            $messages[] = "Bentrok dengan jadwal {$schedule->type->label()} lain ({$schedule->start_at->format('d/m/Y H:i')} - {$schedule->end_at->format('d/m/Y H:i')}).";
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['start_at' => $messages])->errorBag(self::ERROR_BAG);
        }
    }
}
