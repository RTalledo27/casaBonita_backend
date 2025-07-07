<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceDesk\Http\Controllers\ServiceActionController;
use Modules\ServiceDesk\Http\Controllers\ServiceDeskController;
use Modules\ServiceDesk\Http\Controllers\ServiceRequestController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('servicedesks', ServiceDeskController::class)->names('servicedesk');
});

Route::prefix('v1')->group(function () {
    Route::prefix('servicedesk')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('requests', ServiceRequestController::class);
        Route::apiResource('actions',  ServiceActionController::class);
        Route::get('dashboard', [ServiceDeskController::class, 'dashboard'])->name('servicedesk.dashboard');
        Route::get('/requests/{ticket_id}/actions', [ServiceActionController::class, 'index']);
    });
});
 