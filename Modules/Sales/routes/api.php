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
    Route::apiResource('sale', SalesController::class)->names('sales');
});

/**
 * Rutas de ventas
 * @prefix sales
 * @middleware auth:sanctum
 * @group Sales
 * Route::prefix('v1')->group(function () {
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
    });
 */
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
    });
});
