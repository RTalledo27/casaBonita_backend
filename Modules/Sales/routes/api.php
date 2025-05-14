<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\ContractController;
use Modules\Sales\Http\Controllers\PaymentController;
use Modules\Sales\Http\Controllers\PaymentScheduleController;
use Modules\Sales\Http\Controllers\ReservationController;
use Modules\Sales\Http\Controllers\SalesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('sales', SalesController::class)->names('sales');
});


Route::prefix('v1')->group(function () {
    Route::prefix('sales')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('reservations', ReservationController::class);
        Route::apiResource('contracts',    ContractController::class);
        Route::apiResource('schedules',    PaymentScheduleController::class);
        Route::apiResource('payments',     PaymentController::class);
    });
});