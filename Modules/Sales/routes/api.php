<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\ContractApprovalController;
use Modules\Sales\Http\Controllers\ContractController;
use Modules\Sales\Http\Controllers\PaymentController;
use Modules\Sales\Http\Controllers\PaymentScheduleController;
use Modules\Sales\Http\Controllers\ReservationController;
use Modules\Sales\Http\Controllers\SalesController;
use Modules\Sales\Models\ContractApproval;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('sales', SalesController::class)->names('sales');
});


Route::prefix('v1')->group(function () {
    Route::prefix('sales')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('reservations', ReservationController::class);
        Route::apiResource('contracts',    ContractController::class);
        Route::post('reservations/{reservation}/convert', [ReservationController::class, 'convert']);
        Route::apiResource('schedules',    PaymentScheduleController::class);
        Route::apiResource('payments',     PaymentController::class);
        Route::post('contract-approvals/{approval}/approve', [ContractApprovalController::class, 'approve']);
        Route::post('contract-approvals/{approval}/reject',  [ContractApprovalController::class, 'reject']);
    });
});