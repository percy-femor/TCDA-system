<?php

use App\Http\Controllers\Api\DeviceAuthController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [DeviceAuthController::class, 'login']);

Route::middleware('device')->group(function () {
    Route::post('/auth/logout', [DeviceAuthController::class, 'logout']);
    Route::post('/sync/push', [SyncController::class, 'push']);
    Route::get('/sync/pull', [SyncController::class, 'pull']);
});
