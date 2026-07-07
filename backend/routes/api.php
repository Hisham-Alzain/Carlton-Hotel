<?php

use App\Http\Controllers\Auth\GuestAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Staff\PermissionController;
use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Permanent liveness probe
Route::get('/health', function (Request $request) {
    return response()->json([
        'success'    => true,
        'message'    => __('custom.health.ok'),
        'data'       => ['status' => 'ok', 'time' => now()->toIso8601String()],
        'request_id' => $request->attributes->get('request_id', ''),
    ]);
});

// Staff auth
Route::prefix('auth')->group(function () {
    Route::post('/login', [StaffAuthController::class, 'login']);
    Route::middleware('auth:users')->group(function () {
        Route::post('/logout', [StaffAuthController::class, 'logout']);
        Route::get('/me', [StaffAuthController::class, 'me']);
    });

    // Guest auth
    Route::prefix('guest')->group(function () {
        Route::post('/request-otp',      [GuestAuthController::class, 'requestOtp'])->middleware('throttle:10,1');
        Route::post('/verify-otp',       [GuestAuthController::class, 'verifyOtp']);
        Route::post('/link-booking-code',[GuestAuthController::class, 'linkBookingCode']);
    });
});

Route::middleware('auth:users')->group(function () {
    // Staff management
    Route::get   ('/staff',                      [StaffController::class, 'index']);
    Route::post  ('/staff',                      [StaffController::class, 'store']);
    Route::get   ('/staff/{user}',               [StaffController::class, 'show']);
    Route::put   ('/staff/{user}',               [StaffController::class, 'update']);
    Route::post  ('/staff/{user}/permissions',   [StaffController::class, 'assignPermissions']);
    Route::patch ('/staff/{user}/deactivate',    [StaffController::class, 'deactivate']);

    // Reference lists
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/roles',       [RoleController::class, 'index']);
});
