<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\{
    ClientController,
    AddressController,
    CrmInteractionController,
    CRMController,
    FamilyMemberController,
    ClientVerificationController
};


Route::middleware(['auth:sanctum'])->prefix('v1/crm')->group(function () {
    Route::apiResource('crms', CRMController::class)->names('crm');
    
    // CRM specific routes with additional middleware
    Route::middleware(['check.password.change', 'can:crm.access'])->group(function () {
            // CRUD estándar
            Route::apiResource('clients',       ClientController::class);
            Route::apiResource('addresses',     AddressController::class);
            Route::apiResource('interactions',  CrmInteractionController::class);
            Route::apiResource('family-members', FamilyMemberController::class);


        // Endpoints adicionales
        Route::get('clients/{client}/spouses',       [ClientController::class, 'spouses'])
                ->name('clients.spouses.index');
            Route::post('clients/{client}/spouses',      [ClientController::class, 'addSpouse'])
                ->name('clients.spouses.store');
            Route::delete('clients/{client}/spouses/{partner}', [ClientController::class, 'removeSpouse'])
                ->name('clients.spouses.destroy');

            Route::get('clients/{client}/summary',       [ClientController::class, 'summary'])
                ->name('clients.summary');
            Route::get('clients/report/csv',             [ClientController::class, 'exportCsv'])
                ->name('clients.report.csv');

            Route::post('clients/{client_id}/verifications/request', [ClientVerificationController::class, 'requestVerification'])
                ->name('crm.clients.verifications.request');
            Route::post('clients/{client_id}/verifications/confirm', [ClientVerificationController::class, 'confirmVerification'])
                ->name('crm.clients.verifications.confirm');
            Route::post('clients/{client_id}/verifications/resend', [ClientVerificationController::class, 'resendVerification'])
                ->name('crm.clients.verifications.resend');

            Route::post('verifications/request-anon', [ClientVerificationController::class, 'requestAnon'])
                ->name('crm.clients.verifications.request-anon');
            Route::post('verifications/confirm-anon', [ClientVerificationController::class, 'confirmAnon'])
                ->name('crm.clients.verifications.confirm-anon');
    });
});
