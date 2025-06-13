<?php

use Illuminate\Http\Request;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureDeviceIsApproved;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\DeviceApprovalController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
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
});


Route::middleware(['auth:sanctum', EnsureDeviceIsApproved::class])->group(function () {
    // Protected routes for approved devices of staff users
});

