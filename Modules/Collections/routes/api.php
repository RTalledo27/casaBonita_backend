<?php

use Illuminate\Support\Facades\Route;
use Modules\Collections\Http\Controllers\CollectionsController;
use Modules\Collections\Http\Controllers\CustomerPaymentController;
use Modules\Collections\Http\Controllers\AccountReceivableController;
use Modules\Collections\Http\Controllers\HrIntegrationController;

Route::middleware(['auth:sanctum'])->prefix('v1/collections')->group(function () {
    Route::apiResource('collections', CollectionsController::class)->names('collections');
    
    // Rutas para gestión de pagos de clientes
    Route::prefix('customer-payments')->group(function () {
        Route::get('/', [CustomerPaymentController::class, 'index'])
            ->middleware('permission:collections.customer-payments.view')
            ->name('customer-payments.index');
        
        Route::post('/', [CustomerPaymentController::class, 'store'])
            ->middleware('permission:collections.customer-payments.create')
            ->name('customer-payments.store');
        
        Route::get('/{id}', [CustomerPaymentController::class, 'show'])
            ->middleware('permission:collections.customer-payments.view')
            ->name('customer-payments.show');
        
        Route::put('/{id}', [CustomerPaymentController::class, 'update'])
            ->middleware('permission:collections.customer-payments.update')
            ->name('customer-payments.update');
        
        Route::delete('/{id}', [CustomerPaymentController::class, 'destroy'])
            ->middleware('permission:collections.customer-payments.delete')
            ->name('customer-payments.destroy');
        
        Route::post('/{id}/redetect-installment', [CustomerPaymentController::class, 'redetectInstallment'])
            ->middleware('permission:collections.customer-payments.redetect')
            ->name('customer-payments.redetect-installment');
        
        Route::get('/stats/detection', [CustomerPaymentController::class, 'getDetectionStats'])
            ->middleware('permission:collections.customer-payments.view')
            ->name('customer-payments.detection-stats');
    });
    
    // Rutas para gestión de cuentas por cobrar
    Route::prefix('accounts-receivable')->group(function () {
        Route::get('/', [AccountReceivableController::class, 'index'])
            ->middleware('permission:collections.accounts-receivable.view')
            ->name('accounts-receivable.index');
        
        Route::get('/overdue', [AccountReceivableController::class, 'overdue'])
            ->middleware('permission:collections.accounts-receivable.view')
            ->name('accounts-receivable.overdue');
        
        Route::get('/{id}', [AccountReceivableController::class, 'show'])
            ->middleware('permission:collections.accounts-receivable.view')
            ->name('accounts-receivable.show');
    });
    
    // Rutas para integración HR-Collections
    Route::prefix('hr-integration')->group(function () {
        Route::get('/stats', [HrIntegrationController::class, 'stats'])
            ->middleware('permission:collections.hr-integration.view')
            ->name('hr-integration.stats');
        
        Route::post('/sync', [HrIntegrationController::class, 'sync'])
            ->middleware('permission:collections.hr-integration.sync')
            ->name('hr-integration.sync');
        
        Route::get('/process-eligible', [HrIntegrationController::class, 'processEligible'])
            ->middleware('permission:collections.hr-integration.process')
            ->name('hr-integration.process-eligible');
        
        Route::post('/mark-eligible', [HrIntegrationController::class, 'markEligible'])
            ->middleware('permission:collections.hr-integration.mark')
            ->name('hr-integration.mark-eligible');
    });
});
