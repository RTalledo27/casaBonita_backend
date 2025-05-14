<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\AuditController;
use Modules\Audit\Http\Controllers\AuditLogController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('audits', AuditController::class)->names('audit');
});


Route::prefix('v1')->group(function () {
    Route::prefix('audit')->middleware('auth:sanctum')->group(function () {
        Route::get('logs',    [AuditLogController::class, 'index']);
        Route::get('logs/{auditLog}', [AuditLogController::class, 'show']);
    });
});