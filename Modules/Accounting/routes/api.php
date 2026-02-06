<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingController;
use Modules\Accounting\Http\Controllers\BankAccountController;
use Modules\Accounting\Http\Controllers\BankTransactionController;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\InvoiceController;
use Modules\Accounting\Http\Controllers\JournalEntryController;
use Modules\Accounting\Http\Controllers\JournalLineController;

Route::middleware(['auth:sanctum'])->prefix('v1/accounting')->group(function () {
    Route::apiResource('accountings', AccountingController::class)->names('accounting');
    
    // Additional accounting routes
    Route::apiResource('accounts',     ChartOfAccountController::class);
    Route::apiResource('entries',      JournalEntryController::class);
    Route::apiResource('lines',        JournalLineController::class);
    Route::apiResource('bank-accounts', BankAccountController::class);
    Route::apiResource('bank-transactions', BankTransactionController::class);

    // ============================================
    // FACTURACIÓN ELECTRÓNICA SUNAT
    // ============================================
    
    // Dashboard de facturación
    Route::get('billing/dashboard', [InvoiceController::class, 'dashboard']);
    
    // Series disponibles
    Route::get('billing/series', [InvoiceController::class, 'listSeries']);
    
    // Buscar cliente por DNI/RUC
    Route::get('billing/search-client', [InvoiceController::class, 'searchClient']);
    
    // Buscar pagos pendientes
    Route::get('billing/pending-payments', [InvoiceController::class, 'getPendingPayments']);
    
    // Emitir comprobantes
    Route::post('billing/emit-boleta', [InvoiceController::class, 'emitBoleta']);
    Route::post('billing/emit-factura', [InvoiceController::class, 'emitFactura']);
    Route::post('billing/emit-nota-credito', [InvoiceController::class, 'emitNotaCredito']);
    
    // Acciones sobre comprobantes
    Route::get('invoices/{invoice}/xml', [InvoiceController::class, 'downloadXml']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
    Route::post('invoices/{invoice}/resend', [InvoiceController::class, 'resend']);
    
    // CRUD de invoices
    Route::apiResource('invoices', InvoiceController::class);
});
