<?php

use Illuminate\Http\Request;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ServiceController;
use App\Http\Middleware\EnsureDeviceIsApproved;
use App\Http\Controllers\DeviceStatusController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\API\ClinicCalendarController;
use App\Http\Controllers\API\ServiceDiscountController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\DeviceApprovalController;
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
Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());


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
});

Route::middleware(['auth:sanctum', EnsureDeviceIsApproved::class])->group(function () {
    // Protected routes for approved devices of staff users
});

// API routes for services
Route::middleware('auth:sanctum')->get('/services', [ServiceController::class, 'index']);
Route::middleware('auth:sanctum')->get('/services/{service}', [ServiceController::class, 'show']);
