<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Models\ClinicCalendar;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ClinicWeeklySchedule;
use App\Services\ClinicDateResolverService;

class ClinicCalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Only show human-created overrides (e.g., holidays)
        return ClinicCalendar::where('is_generated', false)
            ->orderBy('date')
            ->get();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|unique:clinic_calendar,date',
            'is_open' => 'required|boolean',
            'open_time' => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'max_per_block_override' => 'nullable|integer|min:1|max:50', // NEW
            'note' => 'nullable|string|max:255',
        ]);

        return ClinicCalendar::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $calendar = ClinicCalendar::findOrFail($id);

        $validated = $request->validate([
            'is_open' => 'required|boolean',
            'open_time' => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'max_per_block_override' => 'nullable|integer|min:1|max:50', // NEW
            'note' => 'nullable|string|max:255',
        ]);

        $calendar->update($validated);
        return $calendar;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $calendar = ClinicCalendar::findOrFail($id);
        $calendar->delete();

        return response()->json(['message' => 'Event removed from clinic calendar.']);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'is_open' => 'nullable|boolean',
            'open_time' => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'max_per_block_override' => 'nullable|integer|min:1|max:50',
            'note' => 'nullable|string|max:255',
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $today = now()->startOfDay();
        $min = $today;
        $max = $today->copy()->addDays(13); // 14-day window: today..+13

        if ($date->lt($min) || $date->gt($max)) {
            return response()->json(['message' => 'You can only edit caps for the next 14 days.'], 422);
        }

        // Warn if lowering cap below current peak usage (no auto-cancel)
        $warning = null;
        if (array_key_exists('max_per_block_override', $data) && !is_null($data['max_per_block_override'])) {
            $peak = $this->peakConcurrentForDate($date);
            if ($peak > (int) $data['max_per_block_override']) {
                $warning = "Heads up: existing bookings peak at {$peak}, higher than new cap {$data['max_per_block_override']}. No cancellations were made.";
            }
        }

        // Upsert by date
        ClinicCalendar::updateOrCreate(
            ['date' => $date->toDateString()],
            [
                'is_open' => $data['is_open'] ?? DB::raw('is_open'),
                'open_time' => $data['open_time'] ?? DB::raw('open_time'),
                'close_time' => $data['close_time'] ?? DB::raw('close_time'),
                'max_per_block_override' => $data['max_per_block_override'] ?? null,
                'note' => $data['note'] ?? DB::raw('note'),
            ]
        );

        return response()->json(['ok' => true, 'warning' => $warning]);
    }

    private function peakConcurrentForDate(Carbon $date): int
    {
        $appts = Appointment::whereDate('date', $date->toDateString())
            ->whereIn('status', ['pending', 'approved'])
            ->get(['time_slot']);

        $usage = [];
        foreach ($appts as $a) {
            if (!$a->time_slot || strpos($a->time_slot, '-') === false)
                continue;
            [$aStart, $aEnd] = explode('-', $a->time_slot, 2);
            $cur = Carbon::createFromFormat('H:i', trim($aStart));
            $end = Carbon::createFromFormat('H:i', trim($aEnd));
            while ($cur->lt($end)) {
                $k = $cur->format('H:i');
                $usage[$k] = ($usage[$k] ?? 0) + 1;
                $cur->addMinutes(30);
            }
        }
        return empty($usage) ? 0 : max($usage);
    }

    public function preview(Request $request, ClinicDateResolverService $resolver)
    {
        $days = max(1, min((int) $request->query('days', 14), 14));
        $start = now()->startOfDay();
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $snap = $resolver->resolve($d);
            $out[] = [
                'date' => $d->toDateString(),
                'is_open' => $snap['is_open'],
                'open_time' => $snap['open_time'],
                'close_time' => $snap['close_time'],
                'dentist_count' => $snap['dentist_count'],
                'calendar_max_per_block' => $snap['calendar_max_per_block'],
                'effective_capacity' => $snap['effective_capacity'],
                'bookable_for_patients' => $i >= 1 && $i <= 7,
            ];
        }
        return response()->json($out);
    }

    public function daily(Request $request, ClinicDateResolverService $resolver)
    {
        $days = max(1, min((int) $request->query('days', 14), 31));
        $start = Carbon::parse($request->query('from', now()->toDateString()))->startOfDay();

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $snap = $resolver->resolve($d);

            // Pull any per‑date override row so we can surface its note (e.g., holiday)
            $cal = ClinicCalendar::whereDate('date', $d)->first();

            $out[] = [
                'date' => $d->toDateString(),
                'active_dentists' => (int) $snap['dentist_count'],
                'max_parallel' => $snap['calendar_max_per_block'], // null = no cap
                'is_closed' => !$snap['is_open'],
                'note' => $cal->note ?? null,               // <-- include note
            ];
        }

        return response()->json($out);
    }

    public function upsertDay(Request $request, string $date)
    {
        // UI payload
        $data = $request->validate([
            'max_parallel' => ['nullable', 'integer', 'min:0'],
            'is_closed' => ['nullable', 'boolean'], // ignored by Capacity for persistence rules
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $ymd = Carbon::parse($date)->toDateString();

        // If a manual override exists (holiday, special hours), DO NOT create/convert to generated.
        // Also, do not change is_open/open_time/close_time here.
        $manual = ClinicCalendar::whereDate('date', $ymd)
            ->where('is_generated', false)
            ->first();

        if ($manual) {
            // Capacity uses this entry and does not write a separate generated row.
            // You can choose to let Capacity write only the cap on a manual row or ignore completely.
            // Commonly we ignore to avoid accidental changes on holidays:
            //   return response()->json(['ok' => true, 'info' => 'Manual override exists; capacity not applied.']);
            // If you DO want to allow editing the cap on manual rows, uncomment this block:
            /*
            $manual->update([
                'max_per_block_override' => array_key_exists('max_parallel', $data)
                    ? ($data['max_parallel'] ?? null)
                    : $manual->max_per_block_override,
                'note' => $data['note'] ?? $manual->note,
            ]);
            */
            return response()->json(['ok' => true, 'info' => 'Manual override exists; capacity not generated.']);
        }

        // No manual override: write/update a generated row that ONLY stores the cap & note.
        // IMPORTANT: do NOT touch is_open/open_time/close_time here; those remain governed by
        // Weekly defaults + manual overrides.
        ClinicCalendar::updateOrCreate(
            ['date' => $ymd],
            [
                'is_generated' => true,
                'max_per_block_override' => array_key_exists('max_parallel', $data)
                    ? ($data['max_parallel'] ?? null)
                    : null,
                'note' => $data['note'] ?? null,

                // leave these fields unchanged if a row exists, and don’t set if creating:
                // 'is_open'    => DB::raw('is_open'),
                // 'open_time'  => DB::raw('open_time'),
                // 'close_time' => DB::raw('close_time'),
            ]
        );

        return response()->json(['ok' => true]);
    }


}