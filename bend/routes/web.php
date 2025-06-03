<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
});

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill(); // ✅ mark user as verified
    return redirect(config('app.frontend_url') . '/verify-success'); // 🔁 redirect to frontend
})->middleware(['auth:sanctum', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return response()->json(['message' => 'Verification link sent!']);
})->middleware(['auth:sanctum', 'throttle:6,1'])->name('verification.send');

require __DIR__.'/auth.php';
