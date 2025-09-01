<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;
use Modules\Inventory\Http\Controllers\LotController;
use Modules\Inventory\Http\Controllers\LotMediaController;
use Modules\Inventory\Http\Controllers\ManzanaController;
use Modules\Inventory\Http\Controllers\StreetTypeController;
use Modules\Inventory\Http\Controllers\LotImportController;

Route::middleware(['auth:sanctum'])->prefix('v1/inventory')->group(function () {
    Route::apiResource('inventories', InventoryController::class)->names('inventory');
    
    // Ruta pública para descargar template (sin middleware de auth)
    Route::withoutMiddleware(['auth:sanctum'])->get('lot-import/template', [LotImportController::class, 'downloadTemplate'])->name('lot-import.template');
    
    // Inventory specific routes with additional middleware
    Route::middleware(['check.password.change'])->group(function () {
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
            Route::get('/diagnose-lot-financial-templates', [LotImportController::class, 'diagnoseLotFinancialTemplates'])->name('lot-import.diagnose-templates');
            
            // Diagnóstico de reglas de financiamiento desde Excel
            Route::post('/diagnose-financing-rules', [LotController::class, 'diagnoseFinancingRules']);
            Route::post('/diagnose-column-j', [LotController::class, 'diagnoseColumnJ']);
            Route::get('/history', [LotImportController::class, 'history'])->name('lot-import.history');
            Route::get('/financing-rules', [LotImportController::class, 'getFinancingRules'])->name('lot-import.financing-rules');
            Route::get('/lots-with-financial-data', [LotImportController::class, 'getLotsWithFinancialData'])->name('lot-import.lots-financial');
            Route::delete('/clear-data', [LotImportController::class, 'clearImportData'])->name('lot-import.clear-data');
            
            // Rutas para importación asíncrona
            Route::post('/async', [LotImportController::class, 'asyncImport'])->name('lot-import.async');
            Route::get('/async/{id}/status', [LotImportController::class, 'getAsyncStatus'])->name('lot-import.async-status');
        });
    });
});
