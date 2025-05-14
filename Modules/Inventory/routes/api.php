<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;
use Modules\Inventory\Http\Controllers\LotController;
use Modules\Inventory\Http\Controllers\LotMediaController;
use Modules\Inventory\Http\Controllers\ManzanaController;
use Modules\Inventory\Http\Controllers\StreetTypeController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('inventories', InventoryController::class)->names('inventory');
});


Route::prefix('v1')->group(function () {
    Route::prefix('inventory')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('manzanas',      ManzanaController::class);
        Route::apiResource('street-types', StreetTypeController::class);
        Route::apiResource('lots',        LotController::class);
        Route::apiResource('lot-media',   LotMediaController::class)
            ->only(['index', 'store', 'show', 'destroy']);
    });
});
