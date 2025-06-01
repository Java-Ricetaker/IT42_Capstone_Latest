<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// Registration should stay on 'api' only (no CSRF needed)
Route::post('/register', [RegisteredUserController::class, 'store']);



// Logout uses Sanctum token so no CSRF needed
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);

// Authenticated user fetch
Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());
