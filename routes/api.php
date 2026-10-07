<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\ScanController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

// The mobile API. Documented in docs/API.md; the offline contract in docs/OFFLINE_SYNC.md.
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:mobile-login');

    Route::middleware(['auth:sanctum', 'mobile', 'throttle:mobile'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('reference', ReferenceController::class);
        Route::get('scan/{code}', [ScanController::class, 'scan'])->where('code', '.*');
        Route::get('animals/{code}', [ScanController::class, 'animal'])->where('code', '.*');
        Route::get('tasks', [TaskController::class, 'mine']);
        Route::post('sync/push', [SyncController::class, 'push']);
        Route::get('sync/status', [SyncController::class, 'status']);
        Route::get('sync/mutations/{clientId}', [SyncController::class, 'show'])->whereUuid('clientId');
        Route::post('quick/{type}', [SyncController::class, 'quick']);
    });
});
