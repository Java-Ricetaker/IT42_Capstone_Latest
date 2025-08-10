<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Service;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\ClinicCalendarController;

class AppointmentSlotController extends Controller
{
    /**
     * Returns all valid starting time slots for a given date and service.
     * It considers the service duration, the clinic's open hours for that day,
     * and the current usage of each 30-minute slot based on existing appointments.
     */
    public function get(Request $request)
    {
        // Validate input
        $date = $request->query('date');
        $serviceId = $request->query('service_id');

        if (!$date || !$serviceId) {
            return response()->json(['message' => 'Missing date or service_id.'], 422);
        }

        // Load the service and determine how many 30-minute blocks it needs
        $service = Service::findOrFail($serviceId);
        $blocksNeeded = ceil($service->estimated_minutes / 30);

        // Get resolved clinic hours and dentist count for this date
        $resolved = ClinicCalendarController::smartResolveDate($date);

        if (!$resolved['is_open']) {
            return response()->json(['slots' => []]);
        }

        $open = Carbon::parse($resolved['opening_time']);
        $close = Carbon::parse($resolved['closing_time']);
        $maxPerSlot = (int) $resolved['dentist_count'];


        // Generate all 30-minute blocks between open and close
        $blocks = [];
        $current = clone $open;

        while ($current->copy()->addMinutes(30)->lte($close)) {
            $blocks[] = $current->format('H:i');
            $current->addMinutes(30);
        }


        // \Log::debug('Resolved clinic schedule', [
        //     'input_date' => $date,
        //     'resolved' => $resolved
        // ]);

        // ✅ Early return if service duration doesn't fit in the day
        if ($blocksNeeded > count($blocks)) {
            return response()->json(['slots' => []]);
        }

        // Count how many appointments are using each 30-min slot
        $appointments = Appointment::where('date', $date)->get();
        $slotUsage = []; // e.g. ['08:00' => 1, '08:30' => 2]

        foreach ($appointments as $appt) {
            [$start, $end] = explode('-', $appt->time_slot);
            $startTime = Carbon::parse($start);
            $endTime = Carbon::parse($end);

            while ($startTime->lt($endTime)) {
                $key = $startTime->format('H:i');
                $slotUsage[$key] = ($slotUsage[$key] ?? 0) + 1;
                $startTime->addMinutes(30);
            }
        }

        // Optional: Optimization block using static cache (disabled for now)
        /*
        static $cachedSlotUsage = [];
        $slotUsageKey = $date;

        if (!isset($cachedSlotUsage[$slotUsageKey])) {
            $cachedSlotUsage[$slotUsageKey] = $slotUsage;
        }

        $slotUsage = $cachedSlotUsage[$slotUsageKey];
        */

        // Check each possible start slot to see if enough consecutive blocks are free
        $validStarts = [];

        for ($i = 0; $i <= count($blocks) - $blocksNeeded; $i++) {
            $window = array_slice($blocks, $i, $blocksNeeded);
            $valid = true;

            foreach ($window as $slot) {
                if (($slotUsage[$slot] ?? 0) >= $maxPerSlot) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                $validStarts[] = $blocks[$i]; // Add valid starting slot
            }
        }

        // Return the list of valid starting slots and the service's estimated duration
        return response()->json([
            'slots' => $validStarts,
            'duration_minutes' => $service->estimated_minutes,
        ]);
    }
}
