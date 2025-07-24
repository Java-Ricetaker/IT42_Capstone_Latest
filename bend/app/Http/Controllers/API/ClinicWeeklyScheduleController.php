<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClinicWeeklySchedule;
use Illuminate\Http\Request;

class ClinicWeeklyScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return ClinicWeeklySchedule::orderBy('weekday')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
        $schedule = ClinicWeeklySchedule::findOrFail($id);

        $validated = $request->validate([
            'is_open' => 'required|boolean',
            'open_time' => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'dentist_count' => 'required|integer|min:0|max:20',
            'max_per_slot' => 'nullable|integer|min:1|max:20',
            'note' => 'nullable|string|max:255',
        ]);

        // Enforce backend rule: if closed, max_per_slot must be null
        if ($validated['is_open'] === false) {
            $validated['max_per_slot'] = null;
        } else {
            // If open, max_per_slot must be less than or equal to dentist_count
            if (!isset($validated['max_per_slot'])) {
                $validated['max_per_slot'] = 1; // default if not set
            }

            if ($validated['max_per_slot'] > $validated['dentist_count']) {
                return response()->json([
                    'message' => 'Max per slot cannot exceed number of dentists.'
                ], 422);
            }
        }

        $schedule->update($validated);

        return $schedule;
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
