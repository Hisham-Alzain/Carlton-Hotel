<?php

use App\Exceptions\NotFoundException;
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

// P0 test probes — remove before P1 ships
Route::get('/probe/domain-exception', function () {
    throw new NotFoundException('probe');
});

Route::get('/probe/staff', function (Request $request) {
    return response()->json([
        'success'    => true,
        'data'       => ['guard' => 'users'],
        'request_id' => $request->attributes->get('request_id', ''),
    ]);
})->middleware('auth:users');

Route::get('/probe/guest', function (Request $request) {
    return response()->json([
        'success'    => true,
        'data'       => ['guard' => 'guests'],
        'request_id' => $request->attributes->get('request_id', ''),
    ]);
})->middleware('auth:guests');
