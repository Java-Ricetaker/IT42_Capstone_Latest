<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Patient;
use App\Models\Service;
use App\Models\SystemLog;
use App\Models\Appointment;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Helpers\NotificationService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\ClinicCalendarController;

class AppointmentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date|after:today',
            'start_time' => 'required|string', // e.g., "08:00"
            'payment_method' => ['required', Rule::in(['cash', 'maya', 'hmo'])],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $blocksNeeded = ceil($service->estimated_minutes / 30);
        $date = $validated['date'];
        $startTime = Carbon::parse($validated['start_time']);

        // Resolve clinic calendar for the date
        $resolved = ClinicCalendarController::smartResolveDate($date);

        if (!$resolved['is_open']) {
            return response()->json(['message' => 'Clinic is closed on this date.'], 422);
        }

        $open = Carbon::parse($resolved['opening_time']);
        $close = Carbon::parse($resolved['closing_time']);
        $maxPerSlot = (int) $resolved['dentist_count'];

        // Check if the full time range is within clinic hours
        $endTime = $startTime->copy()->addMinutes($service->estimated_minutes);

        if ($startTime->lt($open) || $endTime->gt($close)) {
            return response()->json(['message' => 'Selected time is outside clinic hours.'], 422);
        }

        // Check if required time blocks are already full
        $appointments = Appointment::where('date', $date)->get();
        $slotUsage = [];

        foreach ($appointments as $appt) {
            [$aStart, $aEnd] = explode('-', $appt->time_slot);
            $aStartTime = Carbon::parse($aStart);
            $aEndTime = Carbon::parse($aEnd);

            while ($aStartTime->lt($aEndTime)) {
                $key = $aStartTime->format('H:i');
                $slotUsage[$key] = ($slotUsage[$key] ?? 0) + 1;
                $aStartTime->addMinutes(30);
            }
        }

        // Build window from requested start time
        $blocks = [];
        $cursor = $startTime->copy();

        for ($i = 0; $i < $blocksNeeded; $i++) {
            $key = $cursor->format('H:i');
            if (($slotUsage[$key] ?? 0) >= $maxPerSlot) {
                return response()->json(['message' => "Time slot starting at $key is already full."], 422);
            }
            $cursor->addMinutes(30);
        }

        // All checks passed — create appointment
        $timeSlot = $startTime->format('H:i') . '-' . $endTime->format('H:i');

        $patient = Patient::byUser(auth()->id());

        if (!$patient) {
            return response()->json([
                'message' => 'Your account is not yet linked to a patient record. Please contact the clinic.',
            ], 422);
        }

        // ✅ Generate reference code
        $referenceCode = strtoupper(Str::random(8));
        
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $validated['service_id'],
            'date' => $date,
            'time_slot' => $timeSlot,
            'reference_code' => $referenceCode,
            'status' => 'pending',
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_method'] === 'maya' ? 'awaiting_payment' : 'unpaid',
        ]);

        return response()->json([
            'message' => 'Appointment booked.',
            'reference_code' => $appointment->reference_code,
            'appointment' => $appointment
        ]);
    }


    // Optional: Add list(), cancel(), approve(), reject() here later

    public function approve($id)
    {
        $appointment = Appointment::findOrFail($id);

        if ($appointment->status !== 'pending') {
            return response()->json(['error' => 'Appointment already processed.'], 422);
        }

        $from = $appointment->status;
        $appointment->status = 'approved';
        $appointment->save();

        SystemLog::create([
            'user_id' => auth()->id(),
            'category' => 'appointment',
            'action' => 'approved',
            'message' => 'Staff ' . auth()->user()->name . ' approved appointment #' . $appointment->id,
            'context' => ['appointment_id' => $appointment->id],
        ]);

        return response()->json(['message' => 'Appointment approved.']);
    }

    public function reject(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        if ($appointment->status !== 'pending') {
            return response()->json(['error' => 'Appointment already processed.'], 422);
        }

        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $from = $appointment->status;
        $appointment->status = 'rejected';
        $appointment->notes = $request->note;
        $appointment->save();


        SystemLog::create([
            'user_id' => auth()->id(),
            'category' => 'appointment',
            'action' => 'rejected',
            'message' => 'Staff ' . auth()->user()->name . ' rejected appointment #' . $appointment->id,
            'context' => [
                'appointment_id' => $appointment->id,
                'note' => $request->note,
            ],
        ]);

        return response()->json(['message' => 'Appointment rejected.']);
    }

    public function index(Request $request)
    {
        $query = Appointment::with(['service', 'patient']);

        // Optional filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Optional filter by date range (future-proofing)
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function userAppointments(Request $request)
    {
        $user = $request->user();

        if (!$user->patient) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                    'per_page' => 10,
                ]
            ]);
        }

        $appointments = Appointment::with('service')
            ->where('patient_id', $user->patient->id)
            ->latest('date')
            ->paginate(10);

        return response()->json($appointments);
    }

    public function cancel($id)
    {
        $user = auth()->user();

        if (!$user->patient) {
            return response()->json(['message' => 'Not linked to patient profile.'], 403);
        }

        $appointment = Appointment::where('id', $id)
            ->where('patient_id', $user->patient->id)
            ->first();

        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found.'], 404);
        }

        if ($appointment->status !== 'pending') {
            return response()->json(['message' => 'Only pending appointments can be canceled.'], 422);
        }

        $appointment->status = 'cancelled';
        $appointment->notes = 'Cancelled by patient.';
        $appointment->canceled_at = now();
        $appointment->save();

        SystemLog::create([
            'user_id' => $user->id,
            'category' => 'appointment',
            'action' => 'canceled_by_patient',
            'message' => 'Patient canceled their appointment #' . $appointment->id,
            'context' => [
                'appointment_id' => $appointment->id,
                'patient_id' => $user->patient->id,
            ]
        ]);

        return response()->json(['message' => 'Appointment canceled.']);
    }

    public function remindable()
    {
        $start = now()->addDays(1)->toDateString();
        $end = now()->addDays(2)->toDateString();

        $appointments = Appointment::with('patient.user', 'service')
            ->whereBetween('date', [$start, $end])
            ->where('status', 'approved')
            ->whereNull('reminded_at')
            ->get();

        return response()->json($appointments);
    }


    public function sendReminder(Request $request, $id)
    {
        $appointment = Appointment::with('patient.user', 'service')->findOrFail($id);

        if (
            $appointment->status !== 'approved' ||
            $appointment->date !== now()->addDays(2)->toDateString() ||
            $appointment->reminded_at !== null
        ) {
            return response()->json(['message' => 'Not eligible for reminder.'], 422);
        }

        $user = $appointment->patient->user;
        if (!$user) {
            return response()->json(['message' => 'Patient has no linked user account.'], 422);
        }

        $message = $request->input('message');
        $edited = $request->input('edited', false); // default to false

        // 🔔 Send the message
        NotificationService::send(
            to: $user->contact_number,
            subject: 'Dental Appointment Reminder',
            message: $message
        );

        $appointment->reminded_at = now();
        $appointment->save();

        // ✅ Log it (only if edited)
        if ($edited) {
            SystemLog::create([
                'user_id' => auth()->id(),
                'category' => 'appointment',
                'action' => 'reminder_sent_custom',
                'message' => 'Staff ' . auth()->user()->name . ' sent a custom reminder for appointment #' . $appointment->id,
                'context' => [
                    'appointment_id' => $appointment->id,
                    'message' => $message,
                ],
            ]);
        }

        return response()->json(['message' => 'Reminder sent.']);
    }

    public function resolveReferenceCode($code)
    {
        $appointment = Appointment::with('service', 'patient')
            ->where('reference_code', $code)
            ->where('status', 'pending') // only unprocessed appointments
            ->first();

        if (!$appointment) {
            return response()->json(['message' => 'Invalid or used reference code.'], 404);
        }

        return response()->json([
            'id' => $appointment->id,
            'patient_name' => $appointment->patient->first_name . ' ' . $appointment->patient->last_name,
            'service_name' => $appointment->service->name,
            'date' => $appointment->date,
            'time_slot' => $appointment->time_slot,
        ]);
    }



}
