<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinanceController;
use Modules\Finance\Http\Controllers\BudgetController;

/*
 |--------------------------------------------------------------------------
 | API Routes
 |--------------------------------------------------------------------------
 |
 | Here is where you can register API routes for your application. These
 | routes are loaded by the RouteServiceProvider within a group which
 | is assigned the "api" middleware group. Enjoy building your API!
 |
 */

Route::middleware(['auth:sanctum'])->prefix('v1/finance')->group(function () {
    Route::apiResource('finances', FinanceController::class)->names('finances');
    
    // Budget routes
    Route::apiResource('budgets', BudgetController::class)->names('budgets');
    Route::get('budgets/summary', [BudgetController::class, 'summary'])->name('budgets.summary');
});
