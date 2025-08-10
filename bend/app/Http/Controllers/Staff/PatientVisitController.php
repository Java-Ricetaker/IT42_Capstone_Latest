<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PatientVisit;
use Illuminate\Http\Request;
use App\Models\Patient;
use Illuminate\Support\Str;


class PatientVisitController extends Controller
{
    // 🟢 List visits (e.g. for tracker)
    public function index()
    {
        $visits = PatientVisit::with(['patient', 'service'])
            ->latest('start_time')
            ->take(50)
            ->get();

        return response()->json($visits);
    }

    // 🟢 Create a new patient visit (start timer)
    public function store(Request $request)
    {
        $visitType = $request->input('visit_type');

        if ($visitType === 'walkin') {
            // ✅ Create placeholder patient
            $patient = Patient::create([
                'first_name' => 'Patient',
                'last_name' => strtoupper(Str::random(6)),
                'user_id' => null,
            ]);

            // ✅ Create the visit
            $visit = PatientVisit::create([
                'patient_id' => $patient->id,
                'service_id' => null, // to be selected later
                'visit_date' => now()->toDateString(),
                'start_time' => now(),
                'status' => 'pending',
            ]);

            return response()->json($visit, 201);
        } elseif ($visitType === 'appointment') {
            // ❌ Appointment flow not yet implemented
            // $request->validate([
            //     'reference_code' => 'required|string|exists:appointments,reference_code',
            // ]);

            // $appointment = \App\Models\Appointment::where('reference_code', $request->reference_code)
            //     ->with('patient', 'service')
            //     ->firstOrFail();

            // $visit = PatientVisit::create([
            //     'patient_id' => $appointment->patient_id,
            //     'service_id' => $appointment->service_id,
            //     'visit_date' => now()->toDateString(),
            //     'start_time' => now(),
            //     'status' => 'pending',
            // ]);

            return response()->json([
                'message' => 'Appointment visit logic not implemented yet.'
            ], 501); // HTTP 501 = Not Implemented
        }

        return response()->json(['message' => 'Invalid visit type.'], 422);
    }

    // 🟡 Update visit details (e.g. service selection)
    public function updatePatient(Request $request, $id)
    {
        $visit = PatientVisit::findOrFail($id);
        $patient = $visit->patient;

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'contact_number' => 'nullable|string|max:20',
            'service_id' => 'nullable|exists:services,id',
        ]);

        $patient->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'contact_number' => $validated['contact_number'],
        ]);

        $visit->update([
            'service_id' => $validated['service_id'],
        ]);

        // Optional audit log (can be saved into a `visit_logs` table or something)
        // \Log::info("Patient visit #{$visit->id} updated by staff", [
        //     'edited_fields' => $validated,
        //     'edited_by' => auth()->user()->id
        // ]);

        return response()->json(['message' => 'Patient updated']);
    }



    // 🟡 Mark a visit as finished (end timer)
    public function finish($id)
    {
        $visit = PatientVisit::findOrFail($id);

        if ($visit->status !== 'pending') {
            return response()->json(['message' => 'Only pending visits can be processed.'], 422);
        }

        $visit->update([
            'end_time' => now(),
            'status' => 'completed',
        ]);

        return response()->json(['message' => 'Visit completed.']);
    }

    // 🔴 Reject visit
    public function reject($id, Request $request)
    {
        $visit = PatientVisit::findOrFail($id);

        if ($visit->status !== 'pending') {
            return response()->json(['message' => 'Only pending visits can be processed.'], 422);
        }

        $visit->update([
            'end_time' => now(),
            'status' => 'rejected',
            'note' => $this->buildRejectionNote($request),

        ]);

        return response()->json(['message' => 'Visit rejected.']);
    }

    private function buildRejectionNote(Request $request)
    {
        $reason = $request->input('reason'); // 'human_error', 'left', 'line_too_long'
        $offered = $request->input('offered_appointment'); // true or false

        if ($reason === 'line_too_long') {
            return "Rejected: Line too long. Offered appointment: " . ($offered ? 'Yes' : 'No');
        }

        return match ($reason) {
            'human_error' => 'Rejected: Human error',
            'left' => 'Rejected: Patient left',
            default => 'Rejected: Unknown reason'
        };
    }

    public function linkToExistingPatient(Request $request, $visitId)
    {
        $request->validate([
            'target_patient_id' => 'required|exists:patients,id',
        ]);

        $visit = PatientVisit::findOrFail($visitId);
        $oldPatient = Patient::findOrFail($visit->patient_id);
        $targetPatient = Patient::findOrFail($request->target_patient_id);

        // Replace the link to the correct patient profile
        $visit->update([
            'patient_id' => $targetPatient->id,
        ]);

        // Log example: "Linked visit from Patient #12 → #4 by Staff #2"
        // In future, insert this into system_logs with performed_by, note, etc.

        // Delete the temporary patient profile
        $oldPatient->delete(); // full delete for now

        return response()->json([
            'message' => 'Visit successfully linked to existing patient profile.',
            'visit' => $visit->load('patient'),
        ]);
    }

}
