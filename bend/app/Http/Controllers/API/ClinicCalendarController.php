<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\ClinicCalendar;
use App\Http\Controllers\Controller;
use App\Models\ClinicWeeklySchedule;

class ClinicCalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return ClinicCalendar::orderBy('date')->get();
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
            'dentist_count' => 'required|integer|min:0|max:20',
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
            'dentist_count' => 'required|integer|min:0|max:20',
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

    public function resolve(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->date)->format('Y-m-d');

        // 1. Check for override in clinic_calendar
        $override = ClinicCalendar::where('date', $date)->first();
        if ($override) {
            return response()->json([
                'source' => 'override',
                'data' => $override,
            ]);
        }

        // 2. Fallback to weekly schedule
        $weekday = Carbon::parse($date)->dayOfWeek; // 0 = Sunday
        $default = ClinicWeeklySchedule::where('weekday', $weekday)->first();

        if ($default) {
            return response()->json([
                'source' => 'weekly',
                'data' => [
                    'date' => $date,
                    'is_open' => $default->is_open,
                    'open_time' => $default->open_time,
                    'close_time' => $default->close_time,
                    'dentist_count' => $default->dentist_count,
                    'note' => $default->note,
                ],
            ]);
        }

        return response()->json([
            'error' => 'No schedule found for the given date.',
        ], 404);
    }
}