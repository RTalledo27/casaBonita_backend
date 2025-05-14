<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\DigitalSignatureController;
use Modules\Integrations\Http\Controllers\IntegrationLogController;
use Modules\Integrations\Http\Controllers\IntegrationsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('integrations', IntegrationsController::class)->names('integrations');
});

Route::prefix('v1')->group(function () {
    Route::prefix('integrations')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('logs',       IntegrationLogController::class);
        Route::apiResource('signatures', DigitalSignatureController::class)
            ->only(['index', 'store', 'show', 'destroy']);
    });
});
