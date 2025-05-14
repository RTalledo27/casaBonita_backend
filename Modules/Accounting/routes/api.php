<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingController;
use Modules\Accounting\Http\Controllers\BankAccountController;
use Modules\Accounting\Http\Controllers\BankTransactionController;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\InvoiceController;
use Modules\Accounting\Http\Controllers\JournalEntryController;
use Modules\Accounting\Http\Controllers\JournalLineController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('accountings', AccountingController::class)->names('accounting');
});

Route::prefix('v1')->group(function () {
    Route::prefix('accounting')->middleware('auth:sanctum')->group(function () {
        Route::apiResource('accounts',     ChartOfAccountController::class);
        Route::apiResource('entries',      JournalEntryController::class);
        Route::apiResource('lines',        JournalLineController::class);
        Route::apiResource('invoices',     InvoiceController::class);
        Route::apiResource('bank-accounts', BankAccountController::class);
        Route::apiResource('bank-transactions', BankTransactionController::class);
    });
});
