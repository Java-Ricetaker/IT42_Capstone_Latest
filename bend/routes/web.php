<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
});

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::get('/test-session', function (Request $request) {
    session(['csrf_check' => 'ok']);
    return response()->json([
        'set' => session('csrf_check')
    ]);
});

Route::post('/test-session', function (Request $request) {
    return response()->json([
        'received' => session('csrf_check')
    ]);
});

require __DIR__.'/auth.php';
