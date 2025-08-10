<?php

use Illuminate\Http\Request;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\PatientController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\AppointmentController;
use App\Http\Middleware\EnsureDeviceIsApproved;
use App\Http\Controllers\DeviceStatusController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\API\ClinicCalendarController;
use App\Http\Controllers\Staff\PatientVisitController;
use App\Http\Controllers\API\AppointmentSlotController;
use App\Http\Controllers\API\ServiceDiscountController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\DeviceApprovalController;
use App\Http\Controllers\API\AppointmentServiceController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\API\ClinicWeeklyScheduleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// Registration should stay on 'api' only (no CSRF needed)
Route::post('/register', [RegisteredUserController::class, 'store']);

Route::post('/login', [AuthenticatedSessionController::class, 'store']);


// Logout uses Sanctum token so no CSRF needed
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);

// Authenticated user fetch
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user()->load('patient'); // eager load the relationship

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'contact_number' => $user->contact_number,
        'role' => $user->role,
        'patient' => $user->patient, // include full patient model if exists
        'is_linked' => optional($user->patient)->is_linked ?? false, // directly available in frontend
    ]);
});



Route::middleware(['auth:sanctum', AdminOnly::class])->group(function () {
    // pending device approvals
    Route::get('/admin/pending-devices', [DeviceApprovalController::class, 'index']);
    Route::post('/admin/approve-device', [DeviceApprovalController::class, 'approve']);
    Route::post('/admin/reject-device', [DeviceApprovalController::class, 'reject']);

    // Approved device management
    Route::get('/approved-devices', [DeviceApprovalController::class, 'approvedDevices']);
    Route::put('/rename-device', [DeviceApprovalController::class, 'renameDevice']);
    Route::post('/revoke-device', [DeviceApprovalController::class, 'revokeDevice']);

    // Staff account management
    Route::post('/admin/staff', [StaffAccountController::class, 'store']);

    // Service management
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

    // Service discount management
    Route::get('/services/{service}/discounts', [ServiceDiscountController::class, 'index']);
    Route::post('/services/{service}/discounts', [ServiceDiscountController::class, 'store']);
    Route::put('/discounts/{id}', [ServiceDiscountController::class, 'update']);
    //Route::delete('/discounts/{id}', [ServiceDiscountController::class, 'destroy']);
    Route::post('/discounts/{id}/launch', [ServiceDiscountController::class, 'launch']);
    Route::post('/discounts/{id}/cancel', [ServiceDiscountController::class, 'cancel']);
    Route::get('/discounts-overview', [ServiceDiscountController::class, 'allActivePromos']);
    // Promo Logs / Archive
    Route::get('/discounts-archive', [ServiceDiscountController::class, 'archive']);

    // Clinic calendar management
    Route::prefix('clinic-calendar')->group(function () {
        Route::get('/', [ClinicCalendarController::class, 'index']);
        Route::post('/', [ClinicCalendarController::class, 'store']);
        Route::put('/{id}', [ClinicCalendarController::class, 'update']);
        Route::delete('/{id}', [ClinicCalendarController::class, 'destroy']);
    });

    Route::get('/weekly-schedule', [ClinicWeeklyScheduleController::class, 'index']);
    Route::patch('/weekly-schedule/{id}', [ClinicWeeklyScheduleController::class, 'update']);
});

// Routes for logged in users
Route::middleware('auth:sanctum')->group(function () {
    // Staff-specific routes
    Route::get('/device-status', [DeviceStatusController::class, 'check']);
    Route::post('/staff/change-password', [App\Http\Controllers\Staff\StaffAccountController::class, 'changePassword']);

    // Clinic calendar resolve route
    Route::get('/clinic-calendar/resolve', [ClinicCalendarController::class, 'resolve']);

    // Appointment
    Route::prefix('appointment')->group(function () {
        Route::get('/available-services', [AppointmentServiceController::class, 'availableServices']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::get('/available-slots', [AppointmentSlotController::class, 'get']);
        Route::post('/{id}/cancel', [AppointmentController::class, 'cancel']);
        Route::get('/resolve/{code}', [AppointmentController::class, 'resolveReferenceCode']);
    });

    // Authenticated Patients Linking
    Route::post('/patients/link-self', [PatientController::class, 'linkSelf']);

    Route::get('/user-appointments', [AppointmentController::class, 'userAppointments']);
    
});

Route::middleware(['auth:sanctum', EnsureDeviceIsApproved::class])->group(function () {
    // Protected routes for approved devices of staff users

    // Patients
    Route::get('/patients', [PatientController::class, 'index']); // list all patients
    Route::post('/patients', [PatientController::class, 'store']); // create new walk-in
    Route::post('/patients/{patient}/link', [PatientController::class, 'linkToUser']); // manual linking
    Route::post('/patients/{id}/flag', [PatientController::class, 'flagReview']); // flag for manual review
    Route::get('/patients/search', [PatientController::class, 'search']);

    // Visits
    Route::prefix('visits')->group(function () {
        Route::get('/', [PatientVisitController::class, 'index']);               // View all visits
        Route::post('/', [PatientVisitController::class, 'store']);              // Start a new visit
        Route::post('/{id}/finish', [PatientVisitController::class, 'finish']);  // Mark visit as complete
        Route::post('/{id}/reject', [PatientVisitController::class, 'reject']);  // Mark visit as rejected
        Route::put('/{id}/update-patient', [PatientVisitController::class, 'updatePatient']);
        Route::post('/{visit}/link-existing', [PatientVisitController::class, 'linkToExistingPatient']);

    });

    // Appointments
    Route::post('/appointments/{id}/approve', [AppointmentController::class, 'approve']);
    Route::post('/appointments/{id}/reject', [AppointmentController::class, 'reject']);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/remindable', [AppointmentController::class, 'remindable']);
    Route::post('/appointments/{id}/send-reminder', [AppointmentController::class, 'sendReminder']);


});

// API routes for services
Route::middleware('auth:sanctum')->get('/services', [ServiceController::class, 'index']);
Route::middleware('auth:sanctum')->get('/services/{service}', [ServiceController::class, 'show']);
