<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\HumanResources\app\Http\Controllers\CommissionController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SalesReportsController;
use App\Http\Controllers\PaymentSchedulesController;
use App\Http\Controllers\ProjectionsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserSessionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Profile API Routes
Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
        Route::get('/notification-preferences', [ProfileController::class, 'getNotificationPreferences']);
        Route::put('/notification-preferences', [ProfileController::class, 'updateNotificationPreferences']);
        Route::get('/activity', [ProfileController::class, 'getActivity']);
    });

    // User Session Routes
    Route::middleware('auth:sanctum')->prefix('sessions')->group(function () {
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

Route::post('/debug-commission/find-testable', [CommissionController::class, 'debugTestPayPart']);
Route::post('/debug-commission/{commission_id}/debug-pay-part', [CommissionController::class, 'debugPayPart']);
Route::post('/debug-commission/{commission_id}/set-approved', [CommissionController::class, 'debugSetApproved']);

// Reports API Routes
Route::prefix('reports')->group(function () {
    // Main Reports Controller
    Route::get('/dashboard', [ReportsController::class, 'dashboard']);
    Route::post('/export', [ReportsController::class, 'export']);
    Route::get('/templates', [ReportsController::class, 'templates']);
    Route::post('/templates', [ReportsController::class, 'createTemplate']);
    Route::put('/templates/{id}', [ReportsController::class, 'updateTemplate']);
    Route::delete('/templates/{id}', [ReportsController::class, 'deleteTemplate']);
    Route::get('/history', [ReportsController::class, 'history']);

    // Sales Reports
    Route::prefix('sales')->group(function () {
        Route::get('/', [SalesReportsController::class, 'index']);
        Route::get('/summary', [SalesReportsController::class, 'summary']);
        Route::get('/by-advisor', [SalesReportsController::class, 'byAdvisor']);
        Route::get('/trends', [SalesReportsController::class, 'trends']);
    });

    // Payment Schedules
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentSchedulesController::class, 'index']);
        Route::get('/overdue', [PaymentSchedulesController::class, 'overdue']);
        Route::get('/calendar', [PaymentSchedulesController::class, 'calendar']);
        Route::get('/statistics', [PaymentSchedulesController::class, 'statistics']);
        Route::put('/{id}/status', [PaymentSchedulesController::class, 'updateStatus']);
    });

    // Projections
    Route::prefix('projections')->group(function () {
        Route::get('/', [ProjectionsController::class, 'index']);
        Route::get('/revenue', [ProjectionsController::class, 'revenue']);
        Route::get('/sales', [ProjectionsController::class, 'sales']);
        Route::get('/collections', [ProjectionsController::class, 'collections']);
        Route::get('/kpis', [ProjectionsController::class, 'kpis']);
        Route::get('/trends', [ProjectionsController::class, 'trends']);
    });
});
