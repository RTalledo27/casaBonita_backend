<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceDesk\Http\Controllers\ServiceActionController;
use Modules\ServiceDesk\Http\Controllers\ServiceDeskController;
use Modules\ServiceDesk\Http\Controllers\ServiceRequestController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('servicedesks', ServiceDeskController::class)->names('servicedesk');
});

Route::prefix('v1')->group(function () {
    Route::prefix('service')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('requests', ServiceRequestController::class);
        Route::apiResource('actions',  ServiceActionController::class);
    });
});
