<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceDesk\Http\Controllers\ServiceActionController;
use Modules\ServiceDesk\Http\Controllers\ServiceDeskController;
use Modules\ServiceDesk\Http\Controllers\ServiceRequestController;
use Modules\ServiceDesk\Http\Controllers\SlaConfigController;
use Modules\ServiceDesk\Http\Controllers\ServiceCategoryController;

Route::middleware(['auth:sanctum'])->prefix('v1/servicedesk')->group(function () {
    Route::apiResource('servicedesks', ServiceDeskController::class)->names('servicedesk');
    
    // Additional servicedesk routes with password change check
    Route::middleware(['check.password.change'])->group(function () {
        Route::apiResource('requests', ServiceRequestController::class);
        Route::apiResource('actions',  ServiceActionController::class);
        Route::get('dashboard', [ServiceDeskController::class, 'dashboard'])->name('servicedesk.dashboard');
        
        // Ticket action endpoints
        Route::post('/requests/{ticket_id}/assign', [ServiceRequestController::class, 'assign'])->name('requests.assign');
        Route::post('/requests/{ticket_id}/status', [ServiceRequestController::class, 'changeStatus'])->name('requests.status');
        Route::post('/requests/{ticket_id}/escalate', [ServiceRequestController::class, 'escalate'])->name('requests.escalate');
        Route::post('/requests/{ticket_id}/comment', [ServiceRequestController::class, 'addComment'])->name('requests.comment');
        Route::get('/requests/{ticket_id}/actions', [ServiceRequestController::class, 'getActions'])->name('requests.actions');
        
        // SLA Configuration routes
        Route::get('/sla-configs', [SlaConfigController::class, 'index'])->name('sla.index');
        Route::put('/sla-configs/{id}', [SlaConfigController::class, 'update'])->name('sla.update');
        Route::post('/sla-configs/bulk', [SlaConfigController::class, 'bulkUpdate'])->name('sla.bulk');
        
        // Service Categories routes
        Route::get('/categories', [ServiceCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/active', [ServiceCategoryController::class, 'active'])->name('categories.active');
        Route::post('/categories', [ServiceCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{id}', [ServiceCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{id}', [ServiceCategoryController::class, 'destroy'])->name('categories.destroy');
        Route::post('/categories/{id}/toggle', [ServiceCategoryController::class, 'toggleStatus'])->name('categories.toggle');
    });
});