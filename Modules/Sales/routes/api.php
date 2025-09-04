<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\ContractApprovalController;
use Modules\Sales\Http\Controllers\ContractController;
use Modules\Sales\Http\Controllers\ContractImportController;
use Modules\Sales\Http\Controllers\PaymentController;
use Modules\Sales\Http\Controllers\PaymentScheduleController;
use Modules\Sales\Http\Controllers\ReservationController;
use Modules\Sales\Http\Controllers\SalesController;
use Modules\Sales\Models\ContractApproval;

Route::middleware(['auth:sanctum'])->prefix('v1/sales')->group(function () {
    Route::apiResource('sale', SalesController::class)->names('sales');
    
    // Sales specific routes
        Route::apiResource('reservations', ReservationController::class);
        
        // Contract specific routes (must be before apiResource)
        Route::get('contracts/with-financing', [ContractController::class, 'withFinancing']);
        Route::apiResource('contracts',    ContractController::class);
        Route::get('contracts/{contract}/preview', [ContractController::class, 'preview']);
        Route::post('contracts/calculate-payment', [ContractController::class, 'calculatePayment']);
        Route::post('contracts/{contract}/generate-schedule', [ContractController::class, 'generateSchedule']);
        Route::post('reservations/{reservation}/convert', [ReservationController::class, 'convert']);
        Route::post('reservations/{reservation}/confirm-payment', [ReservationController::class, 'confirmPayment']);
        Route::apiResource('schedules',    PaymentScheduleController::class);
        Route::post('schedules/generate-intelligent', [PaymentScheduleController::class, 'generateIntelligentSchedule']);
        Route::get('contracts/{contract}/financing-options', [PaymentScheduleController::class, 'getFinancingOptions']);
        Route::patch('schedules/{schedule}/mark-paid', [PaymentScheduleController::class, 'markAsPaid']);
        Route::get('schedules/metrics', [PaymentScheduleController::class, 'getMetrics']);
        Route::get('schedules/report', [PaymentScheduleController::class, 'getReport']);
        Route::get('schedules/generate-report', [PaymentScheduleController::class, 'generateReport']);
        Route::get('contracts/{contract}/schedules', [PaymentScheduleController::class, 'getContractSchedules']);
        Route::apiResource('payments',     PaymentController::class);
        Route::post('contract-approvals/{approval}/approve', [ContractApprovalController::class, 'approve']);
        Route::post('contract-approvals/{approval}/reject',  [ContractApprovalController::class, 'reject']);
        
        // Rutas de importación de contratos
        Route::prefix('import')->group(function () {
            Route::post('contracts', [ContractImportController::class, 'import']);
            Route::post('contracts/async', [ContractImportController::class, 'importAsync']);
            Route::post('contracts/validate', [ContractImportController::class, 'validateStructure']);
            Route::post('contracts/validate-simplified', [ContractImportController::class, 'validateStructureSimplified']);
            Route::get('contracts/template', [ContractImportController::class, 'downloadTemplate']);
            Route::get('contracts/template-simplified', [ContractImportController::class, 'downloadSimplifiedTemplate']);
            Route::get('contracts/history', [ContractImportController::class, 'getImportHistory']);
            Route::get('contracts/stats', [ContractImportController::class, 'getImportStats']);
            Route::get('contracts/status/{importLogId}', [ContractImportController::class, 'getImportStatus']);
        });
});
