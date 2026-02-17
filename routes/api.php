<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\HumanResources\app\Http\Controllers\CommissionController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SalesReportsController;
use App\Http\Controllers\PaymentSchedulesController;
use App\Http\Controllers\ProjectionsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Api\LogicwareLotImportController;
use App\Http\Controllers\Api\LogicwareImportController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Password Reset Routes (sin autenticación)
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
Route::post('/verify-reset-token', [ResetPasswordController::class, 'verifyToken']);

// Webhook de Logicware (sin autenticación - validación por firma HMAC)
Route::post('/webhooks/logicware', [WebhookController::class, 'handleLogicwareWebhook']);

// Profile API Routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Dashboard home stats (sin permisos especiales, cualquier usuario autenticado)
    Route::get('dashboard/stats', function () {
        return response()->json([
            'contracts' => [
                'vigente' => \Modules\Sales\Models\Contract::where('status', 'vigente')->count(),
                'pendiente' => \Modules\Sales\Models\Contract::where('status', 'pendiente_aprobacion')->count(),
                'total' => \Modules\Sales\Models\Contract::count(),
            ],
            'lots' => [
                'disponible' => \Modules\Inventory\Models\Lot::where('status', 'disponible')->count(),
                'reservado' => \Modules\Inventory\Models\Lot::where('status', 'reservado')->count(),
                'vendido' => \Modules\Inventory\Models\Lot::where('status', 'vendido')->count(),
                'total' => \Modules\Inventory\Models\Lot::count(),
            ],
            'clients' => [
                'total' => \Modules\CRM\Models\Client::count(),
            ],
            'reservations' => [
                'activa' => \Modules\Sales\Models\Reservation::where('status', 'activa')->count(),
                'convertida' => \Modules\Sales\Models\Reservation::where('status', 'convertida')->count(),
            ],
            'payments' => [
                'pendiente' => \Modules\Collections\Models\PaymentSchedule::where('status', 'pendiente')->count(),
                'vencido' => \Modules\Collections\Models\PaymentSchedule::where('status', 'vencido')->count(),
                'pagado' => \Modules\Collections\Models\PaymentSchedule::where('status', 'pagado')->count(),
            ],
        ]);
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
        Route::get('/notification-preferences', [ProfileController::class, 'getNotificationPreferences']);
        Route::put('/notification-preferences', [ProfileController::class, 'updateNotificationPreferences']);
        Route::get('/activity', [ProfileController::class, 'getActivity']);
    });

    // User Session Routes
    Route::prefix('sessions')->group(function () {
        Route::get('/active', [UserSessionController::class, 'getActiveSession']);
        Route::post('/start', [UserSessionController::class, 'startSession']);
        Route::post('/end', [UserSessionController::class, 'endSession']);
        Route::post('/pause', [UserSessionController::class, 'pauseSession']);
        Route::post('/resume', [UserSessionController::class, 'resumeSession']);
        Route::post('/heartbeat', [UserSessionController::class, 'updateActivity']);
        Route::get('/stats', [UserSessionController::class, 'getStats']);
        Route::get('/history', [UserSessionController::class, 'getHistory']);
    });
});

// Notifications API Routes
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/stats', [NotificationController::class, 'stats']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
    
    // Ruta de prueba (comentar en producción)
    Route::post('/test', [NotificationController::class, 'createTest']);
});

// Debug Routes (Comentar en producción)
Route::post('/debug-commission/find-testable', [CommissionController::class, 'debugTestPayPart']);
Route::post('/debug-commission/{commission_id}/debug-pay-part', [CommissionController::class, 'debugPayPart']);
Route::post('/debug-commission/{commission_id}/set-approved', [CommissionController::class, 'debugSetApproved']);

// Reports API Routes - MODULE REPORTS
Route::middleware(['auth:sanctum', 'permission:reports.access'])->prefix('reports')->group(function () {
    // Main Reports Controller
    Route::get('/dashboard', [ReportsController::class, 'dashboard'])->middleware('permission:reports.view_dashboard');
    Route::post('/export', [ReportsController::class, 'export'])->middleware('permission:reports.export');
    Route::get('/templates', [ReportsController::class, 'templates'])->middleware('permission:reports.view');
    Route::post('/templates', [ReportsController::class, 'createTemplate'])->middleware('permission:reports.view');
    Route::put('/templates/{id}', [ReportsController::class, 'updateTemplate'])->middleware('permission:reports.view');
    Route::delete('/templates/{id}', [ReportsController::class, 'deleteTemplate'])->middleware('permission:reports.view');
    Route::get('/history', [ReportsController::class, 'history'])->middleware('permission:reports.view');

    // New consolidated and export routes
    Route::get('/sales/consolidated', [ReportsController::class, 'getSalesConsolidated'])->middleware('permission:reports.view_sales');
    Route::get('/projections/monthly', [ReportsController::class, 'getProjectionsMonthly'])->middleware('permission:reports.view_projections');
    
    // Excel Export Routes (specific formats from user images)
    Route::get('/export/monthly-income', [ReportsController::class, 'exportMonthlyIncome'])->middleware('permission:reports.export');
    Route::get('/export/detailed-sales', [ReportsController::class, 'exportDetailedSales'])->middleware('permission:reports.export');
    Route::get('/export/client-details', [ReportsController::class, 'exportClientDetails'])->middleware('permission:reports.export');

    // Sales Reports
    Route::prefix('sales')->middleware('permission:reports.view_sales')->group(function () {
        Route::get('/', [SalesReportsController::class, 'index']);
        Route::get('/summary', [SalesReportsController::class, 'summary']);
        Route::get('/by-advisor', [SalesReportsController::class, 'byAdvisor']);
        Route::get('/trends', [SalesReportsController::class, 'trends']);
    });

    // Payment Schedules
    Route::prefix('payments')->middleware('permission:reports.view_payments')->group(function () {
        Route::get('/', [PaymentSchedulesController::class, 'index']);
        Route::get('/overdue', [PaymentSchedulesController::class, 'overdue']);
        Route::get('/calendar', [PaymentSchedulesController::class, 'calendar']);
        Route::get('/statistics', [PaymentSchedulesController::class, 'statistics']);
        Route::put('/{id}/status', [PaymentSchedulesController::class, 'updateStatus']);
    });

    // Projections
    Route::prefix('projections')->middleware('permission:reports.view_projections')->group(function () {
        Route::get('/', [ProjectionsController::class, 'index']);
        Route::get('/revenue', [ProjectionsController::class, 'revenue']);
        Route::get('/sales', [ProjectionsController::class, 'sales']);
        Route::get('/collections', [ProjectionsController::class, 'collections']);
        Route::get('/kpis', [ProjectionsController::class, 'kpis']);
        Route::get('/trends', [ProjectionsController::class, 'trends']);
    });
});

// LogicWare Integration API Routes
Route::middleware(['auth:sanctum'])->prefix('logicware')->group(function () {
    // Importación de Contratos desde Logicware
    Route::post('/import-contracts', [LogicwareImportController::class, 'importContracts'])
        ->middleware('permission:sales.contracts.store'); // Solo usuarios con permiso de crear contratos
    
    Route::get('/status', [LogicwareImportController::class, 'getStatus']);
    
    // Stock Completo con TODOS los datos
    Route::get('/full-stock', [LogicwareImportController::class, 'getFullStock']);
    
    // Token Management
    Route::post('/renew-token', [LogicwareImportController::class, 'renewToken']);
    Route::get('/token-info', [LogicwareImportController::class, 'getTokenInfo']);
    
    // Stages (Etapas)
    Route::get('/stages', [LogicwareLotImportController::class, 'getStages']);
    
    // Stock por Stage
    Route::get('/stages/{stageId}/preview', [LogicwareLotImportController::class, 'previewStageStock']);
    Route::post('/stages/{stageId}/import', [LogicwareLotImportController::class, 'importStage'])
        ->middleware('permission:inventory.lots.store'); // Solo usuarios con permiso de crear lotes
    
    // Utilidades
    Route::get('/connection-stats', [LogicwareLotImportController::class, 'getConnectionStats']);
    Route::post('/clear-cache', [LogicwareLotImportController::class, 'clearCache'])
        ->middleware('permission:inventory.lots.store'); // Solo usuarios con permiso de crear lotes
    
    // Logs de Webhooks (solo para administradores)
    Route::get('/webhooks/logs', [WebhookController::class, 'getLogs'])
        ->middleware('permission:inventory.lots.store');
    Route::get('/webhooks/logs/{messageId}', [WebhookController::class, 'getLogDetail'])
        ->middleware('permission:inventory.lots.store');
});

// Sales Cuts API - Cortes de Ventas Diarios
Route::middleware('auth:sanctum')->prefix('v1/sales/cuts')->group(function () {
    // Listado y consultas
    Route::get('/', [\App\Http\Controllers\Api\SalesCutController::class, 'index']);
    Route::get('/today', [\App\Http\Controllers\Api\SalesCutController::class, 'today']);
    Route::get('/monthly-stats', [\App\Http\Controllers\Api\SalesCutController::class, 'monthlyStats']);
    Route::get('/{id}', [\App\Http\Controllers\Api\SalesCutController::class, 'show']);
    Route::get('/{id}/export', [\App\Http\Controllers\Api\SalesCutController::class, 'export']);
    
    // Nuevos endpoints para cálculo flexible
    Route::post('/calculate', [\App\Http\Controllers\Api\SalesCutController::class, 'calculate']); // Preview sin guardar
    Route::post('/', [\App\Http\Controllers\Api\SalesCutController::class, 'store']); // Crear y guardar
    Route::put('/{id}/recalculate', [\App\Http\Controllers\Api\SalesCutController::class, 'recalculate']); // Recalcular existente
    
    // Endpoints legacy (mantener compatibilidad)
    Route::post('/create-daily', [\App\Http\Controllers\Api\SalesCutController::class, 'createDaily']);
    Route::post('/{id}/close', [\App\Http\Controllers\Api\SalesCutController::class, 'close']);
    Route::post('/{id}/review', [\App\Http\Controllers\Api\SalesCutController::class, 'review']);
    Route::patch('/{id}/notes', [\App\Http\Controllers\Api\SalesCutController::class, 'updateNotes']);
});

// Commission schemes & rules - HumanResources admin APIs
Route::middleware('auth:sanctum')->prefix('v1/hr')->group(function () {
    Route::get('/commission-schemes', [\Modules\HumanResources\Http\Controllers\CommissionSchemeController::class, 'index']);
    Route::get('/commission-schemes/{id}', [\Modules\HumanResources\Http\Controllers\CommissionSchemeController::class, 'show']);
    Route::post('/commission-schemes', [\Modules\HumanResources\Http\Controllers\CommissionSchemeController::class, 'store']);
    Route::put('/commission-schemes/{id}', [\Modules\HumanResources\Http\Controllers\CommissionSchemeController::class, 'update']);
    Route::delete('/commission-schemes/{id}', [\Modules\HumanResources\Http\Controllers\CommissionSchemeController::class, 'destroy']);

    Route::get('/commission-rules', [\Modules\HumanResources\Http\Controllers\CommissionRuleController::class, 'index']);
    Route::get('/commission-rules/{id}', [\Modules\HumanResources\Http\Controllers\CommissionRuleController::class, 'show']);
    Route::post('/commission-rules', [\Modules\HumanResources\Http\Controllers\CommissionRuleController::class, 'store']);
    Route::put('/commission-rules/{id}', [\Modules\HumanResources\Http\Controllers\CommissionRuleController::class, 'update']);
    Route::delete('/commission-rules/{id}', [\Modules\HumanResources\Http\Controllers\CommissionRuleController::class, 'destroy']);
});
