<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;
use Modules\Inventory\Http\Controllers\LotController;
use Modules\Inventory\Http\Controllers\LotMediaController;
use Modules\Inventory\Http\Controllers\ManzanaController;
use Modules\Inventory\Http\Controllers\StreetTypeController;
use Modules\Inventory\Http\Controllers\LotImportController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('inventories', InventoryController::class)->names('inventory');
});


Route::prefix('v1')->group(function () {
    // Ruta pública para descargar template
    Route::get('inventory/lot-import/template', [LotImportController::class, 'downloadTemplate'])->name('lot-import.template');
    
    Route::prefix('inventory')->middleware(['auth:sanctum', 'check.password.change'])->group(function () {
        Route::apiResource('manzanas',      ManzanaController::class);
        Route::apiResource('street-types', StreetTypeController::class);
        Route::apiResource('lots',        LotController::class);
        Route::apiResource('lot-media',   LotMediaController::class);
        
        // Rutas adicionales para lotes
        Route::get('lots/catalog', [LotController::class, 'catalog'])->name('lots.catalog');
        Route::post('lots/financing-simulator', [LotController::class, 'financingSimulator'])->name('lots.financing-simulator');
        Route::get('lots/{lot}/financial-template', [LotController::class, 'getFinancialTemplate'])->name('lots.financial-template');
        Route::get('lots/manzana-financing-rules', [LotController::class, 'getManzanaFinancingRules'])->name('lots.manzana-financing-rules');
        
        // Rutas para importador de lotes
        Route::prefix('lot-import')->group(function () {
            Route::post('/', [LotImportController::class, 'import'])->name('lot-import.import');
            Route::post('/validate', [LotImportController::class, 'validateFile'])->name('lot-import.validate');
            Route::get('/statistics', [LotImportController::class, 'getStatistics'])->name('lot-import.statistics');
            Route::get('/financing-rules', [LotImportController::class, 'getFinancingRules'])->name('lot-import.financing-rules');
            Route::get('/lots-with-financial-data', [LotImportController::class, 'getLotsWithFinancialData'])->name('lot-import.lots-financial');
            Route::delete('/clear-data', [LotImportController::class, 'clearImportData'])->name('lot-import.clear-data');
        });
    });
});
