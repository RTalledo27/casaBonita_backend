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

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('sale', SalesController::class)->names('sales');
});

Route::prefix('v1')->group(function () {
    Route::prefix('sales')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('reservations', ReservationController::class);
        Route::apiResource('contracts',    ContractController::class);
        Route::get('contracts/{contract}/preview', [ContractController::class, 'preview']);
        Route::post('contracts/calculate-payment', [ContractController::class, 'calculatePayment']);
        Route::post('reservations/{reservation}/convert', [ReservationController::class, 'convert']);
        Route::post('reservations/{reservation}/confirm-payment', [ReservationController::class, 'confirmPayment']);
        Route::apiResource('schedules',    PaymentScheduleController::class);
        Route::apiResource('payments',     PaymentController::class);
        Route::post('contract-approvals/{approval}/approve', [ContractApprovalController::class, 'approve']);
        Route::post('contract-approvals/{approval}/reject',  [ContractApprovalController::class, 'reject']);
        
        // Rutas de importación de contratos
        Route::prefix('import')->group(function () {
            Route::post('contracts', [ContractImportController::class, 'import']);
            Route::post('contracts/async', [ContractImportController::class, 'importAsync']);
            Route::post('contracts/validate', [ContractImportController::class, 'validateStructure']);
            Route::get('contracts/template', [ContractImportController::class, 'downloadTemplate']);
            Route::get('contracts/history', [ContractImportController::class, 'getImportHistory']);
            Route::get('contracts/stats', [ContractImportController::class, 'getImportStats']);
            Route::get('contracts/status/{importLogId}', [ContractImportController::class, 'getImportStatus']);
        });
    });
});
