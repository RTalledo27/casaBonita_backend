<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\ClientController;
use Modules\CRM\Http\Controllers\CRMController;
use Modules\CRM\Models\Client;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('crms', CRMController::class)->names('crm');
});


Route::prefix('v1')->group(function () {
    Route::prefix('crms')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('clients', ClientController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);        
    });
        
});