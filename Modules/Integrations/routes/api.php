<?php

use Illuminate\Support\Facades\Route;
use Modules\Integrations\Http\Controllers\DigitalSignatureController;
use Modules\Integrations\Http\Controllers\IntegrationLogController;
use Modules\Integrations\Http\Controllers\IntegrationsController;
use Modules\Integrations\Http\Controllers\ClicklabController;

Route::middleware(['auth:sanctum'])->prefix('v1/integrations')->group(function () {
    Route::apiResource('integrations', IntegrationsController::class)->names('integrations');
    
    // Additional integrations routes
    Route::apiResource('logs',       IntegrationLogController::class);
    Route::apiResource('signatures', DigitalSignatureController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::post('clicklab/ping', [ClicklabController::class, 'ping']);
});
