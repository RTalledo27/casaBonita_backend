<?php

use Illuminate\Support\Facades\Route;
use Modules\Collections\Http\Controllers\CollectionsController;
use Modules\Collections\Http\Controllers\CollectionsDashboardController;
use Modules\Collections\Http\Controllers\CustomerPaymentController;
use Modules\Collections\Http\Controllers\AccountReceivableController;
use Modules\Collections\Http\Controllers\HrIntegrationController;

// Temporary test routes without authentication for debugging
Route::prefix('v1/collections/test')->group(function () {
    Route::get('/dashboard', [CollectionsDashboardController::class, 'getDashboard'])
        ->name('collections.test.dashboard');
    
    Route::get('/dashboard/metrics', [CollectionsDashboardController::class, 'getDashboardMetrics'])
        ->name('collections.test.dashboard.metrics');
    
    Route::get('/dashboard/trends', [CollectionsDashboardController::class, 'getTrends'])
        ->name('collections.test.dashboard.trends');
    
    Route::get('/reports/collection-efficiency', function () {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Reporte de eficiencia de cobranza generado exitosamente',
                'data' => [
                    'period' => [
                        'start_date' => now()->startOfMonth()->format('Y-m-d'),
                        'end_date' => now()->endOfMonth()->format('Y-m-d')
                    ],
                    'schedules_due_in_period' => [
                        'total_count' => 0,
                        'total_amount' => 0,
                        'paid_count' => 0,
                        'paid_amount' => 0
                    ],
                    'payments_received_in_period' => [
                        'total_count' => 0,
                        'total_amount' => 0,
                        'on_time_count' => 0,
                        'late_count' => 0
                    ],
                    'efficiency_metrics' => [
                        'collection_rate' => 0,
                        'on_time_payment_rate' => 0,
                        'average_days_to_collect' => 0
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    })->name('collections.test.collection-efficiency-report');
});

Route::middleware(['auth:sanctum'])->prefix('v1/collections')->group(function () {
    Route::apiResource('collections', CollectionsController::class)->names('collections');
    
    // Rutas para generación de cronogramas
    Route::post('/contracts/{contract_id}/generate-schedule', [CollectionsController::class, 'generateSchedule'])
        ->middleware('permission:collections.schedules.create')
        ->name('collections.generate-schedule');
        
    Route::post('/contracts/generate-bulk-schedules', [CollectionsController::class, 'generateBulkSchedules'])
        ->middleware('permission:collections.schedules.create')
        ->name('collections.generate-bulk-schedules');
    
    Route::get('/generation-stats', [CollectionsController::class, 'getGenerationStats'])
        ->middleware('permission:collections.schedules.view')
        ->name('collections.generation-stats');
        
    // Rutas para gestión de cuotas
    Route::get('/contracts-with-schedules-summary', [CollectionsController::class, 'getContractsWithSchedulesSummary'])
        ->middleware('permission:collections.schedules.view')
        ->name('collections.contracts-schedules-summary');
        
    Route::get('/contracts/{contract_id}/schedules', [CollectionsController::class, 'getContractSchedules'])
        ->middleware('permission:collections.schedules.view')
        ->name('collections.contract-schedules');
        
    Route::put('/schedules/{schedule_id}', [CollectionsController::class, 'updateSchedule'])
        ->middleware('permission:collections.schedules.edit')
        ->name('collections.update-schedule');
        
    Route::delete('/schedules/{schedule_id}', [CollectionsController::class, 'deleteSchedule'])
        ->middleware('permission:collections.schedules.delete')
        ->name('collections.delete-schedule');
        
    Route::post('/schedules/{schedule_id}/mark-paid', [CollectionsController::class, 'markScheduleAsPaid'])
        ->middleware('permission:collections.schedules.edit')
        ->name('collections.mark-schedule-paid');
        
    Route::post('/schedules/{schedule_id}/mark-overdue', [CollectionsController::class, 'markScheduleAsOverdue'])
        ->middleware('permission:collections.schedules.edit')
        ->name('collections.mark-schedule-overdue');
        
    // Rutas para reportes
    Route::get('/reports/payment-summary', [CollectionsController::class, 'getPaymentSummaryReport'])
        ->middleware('permission:collections.reports.view')
        ->name('collections.payment-summary-report');
        
    Route::get('/reports/overdue-analysis', [CollectionsController::class, 'getOverdueAnalysisReport'])
        ->middleware('permission:collections.reports.view')
        ->name('collections.overdue-analysis-report');
        
    Route::get('/reports/collection-efficiency', [CollectionsController::class, 'getCollectionEfficiencyReport'])
        ->middleware('permission:collections.reports.view')
        ->name('collections.collection-efficiency-report');
        
    Route::get('/reports/aging-report', [CollectionsController::class, 'getAgingReport'])
        ->middleware('permission:collections.reports.view')
        ->name('collections.aging-report');
        
    Route::get('/predictions', [CollectionsController::class, 'getCollectionPredictions'])
        ->middleware('permission:collections.reports.view')
        ->name('collections.predictions');

    // Followups (gestión de cobranza)
    Route::prefix('followups')->group(function () {
        Route::get('/', [\Modules\Collections\app\Http\Controllers\FollowupsController::class, 'index'])
            ->name('collections.followups.index');
        Route::post('/', [\Modules\Collections\app\Http\Controllers\FollowupsController::class, 'store'])
            ->name('collections.followups.store');
        Route::put('/{id}', [\Modules\Collections\app\Http\Controllers\FollowupsController::class, 'update'])
            ->name('collections.followups.update');
        Route::put('/{id}/commitment', [\Modules\Collections\app\Http\Controllers\FollowupsController::class, 'updateCommitment'])
            ->name('collections.followups.commitment');
    });

    // Segmentación
    Route::get('/segments/preventive', [\Modules\Collections\app\Http\Controllers\SegmentsController::class, 'preventive'])
        ->name('collections.segments.preventive');
    Route::get('/segments/mora', [\Modules\Collections\app\Http\Controllers\SegmentsController::class, 'mora'])
        ->name('collections.segments.mora');

    // Logs de gestión
    Route::post('/followup-logs', [\Modules\Collections\app\Http\Controllers\FollowupLogsController::class, 'store'])
        ->name('collections.followup-logs.store');

    // KPIs
    Route::get('/kpis', [\Modules\Collections\app\Http\Controllers\KpisController::class, 'index'])
        ->name('collections.kpis.index');
    
    // Dashboard routes
    Route::get('/dashboard', [CollectionsDashboardController::class, 'getDashboard'])
        ->middleware('permission:collections.dashboard.view')
        ->name('collections.dashboard');
        
    Route::get('/dashboard/metrics', [CollectionsDashboardController::class, 'getDashboardMetrics'])
        ->middleware('permission:collections.dashboard.view')
        ->name('collections.dashboard.metrics');
    
    Route::get('/dashboard/upcoming-schedules', [CollectionsDashboardController::class, 'getUpcomingSchedules'])
        ->middleware('permission:collections.dashboard.view')
        ->name('collections.dashboard.upcoming-schedules');
    
    Route::get('/dashboard/overdue-schedules', [CollectionsDashboardController::class, 'getOverdueSchedules'])
        ->middleware('permission:collections.dashboard.view')
        ->name('collections.dashboard.overdue-schedules');
    
    Route::get('/dashboard/collections-summary', [CollectionsDashboardController::class, 'getCollectionsSummary'])
        ->middleware('permission:collections.dashboard.view')
        ->name('collections.dashboard.collections-summary');
    
    Route::get('/dashboard/trends', [CollectionsDashboardController::class, 'getTrends'])
        ->middleware('permission:collections.dashboard.view')
        ->name('collections.dashboard.trends');

    Route::prefix('notifications')->group(function () {
        Route::post('/schedules/{schedule_id}/send-reminder', [\Modules\Collections\Http\Controllers\NotificationsController::class, 'sendScheduleReminder'])
            ->middleware('permission:collections.schedules.view')
            ->name('collections.notifications.send-reminder');
        Route::post('/send-upcoming', [\Modules\Collections\Http\Controllers\NotificationsController::class, 'sendUpcomingReminders'])
            ->middleware('permission:collections.schedules.view')
            ->name('collections.notifications.send-upcoming');
        Route::post('/send-custom', [\Modules\Collections\Http\Controllers\NotificationsController::class, 'sendCustomEmail'])
            ->middleware('permission:collections.schedules.view')
            ->name('collections.notifications.send-custom');
        Route::post('/schedules/{schedule_id}/send-custom', [\Modules\Collections\Http\Controllers\NotificationsController::class, 'sendCustomEmailForSchedule'])
            ->middleware('permission:collections.schedules.view')
            ->name('collections.notifications.send-custom-for-schedule');
        Route::post('/logs/{log_id}/confirm', [\Modules\Collections\Http\Controllers\NotificationsController::class, 'confirmReception'])
            ->middleware('permission:collections.schedules.view')
            ->name('collections.notifications.confirm-reception');
    });
    
    // Rutas para gestión de pagos de clientes
    Route::prefix('customer-payments')->group(function () {
        Route::get('/', [CustomerPaymentController::class, 'index'])
            ->middleware('permission:collections.customer-payments.view')
            ->name('customer-payments.index');
        
        Route::post('/', [CustomerPaymentController::class, 'store'])
            ->middleware('permission:collections.customer-payments.create')
            ->name('customer-payments.store');
        
        Route::get('/{id}', [CustomerPaymentController::class, 'show'])
            ->middleware('permission:collections.customer-payments.view')
            ->name('customer-payments.show');
        
        Route::put('/{id}', [CustomerPaymentController::class, 'update'])
            ->middleware('permission:collections.customer-payments.update')
            ->name('customer-payments.update');
        
        Route::delete('/{id}', [CustomerPaymentController::class, 'destroy'])
            ->middleware('permission:collections.customer-payments.delete')
            ->name('customer-payments.destroy');
        
        Route::post('/{id}/redetect-installment', [CustomerPaymentController::class, 'redetectInstallment'])
            ->middleware('permission:collections.customer-payments.redetect')
            ->name('customer-payments.redetect-installment');
        
        Route::get('/stats/detection', [CustomerPaymentController::class, 'getDetectionStats'])
            ->middleware('permission:collections.customer-payments.view')
            ->name('customer-payments.detection-stats');
    });
    
    // Rutas para gestión de cuentas por cobrar
    Route::prefix('accounts-receivable')->group(function () {
        Route::get('/', [AccountReceivableController::class, 'index'])
            ->middleware('permission:collections.accounts-receivable.view')
            ->name('accounts-receivable.index');
        
        Route::get('/overdue', [AccountReceivableController::class, 'overdue'])
            ->middleware('permission:collections.accounts-receivable.view')
            ->name('accounts-receivable.overdue');
        
        Route::get('/{id}', [AccountReceivableController::class, 'show'])
            ->middleware('permission:collections.accounts-receivable.view')
            ->name('accounts-receivable.show');
    });
    
    // Rutas para integración HR-Collections
    Route::prefix('hr-integration')->group(function () {
        Route::get('/stats', [HrIntegrationController::class, 'stats'])
            ->middleware('permission:collections.hr-integration.view')
            ->name('hr-integration.stats');
        
        Route::post('/sync', [HrIntegrationController::class, 'sync'])
            ->middleware('permission:collections.hr-integration.sync')
            ->name('hr-integration.sync');
        
        Route::get('/process-eligible', [HrIntegrationController::class, 'processEligible'])
            ->middleware('permission:collections.hr-integration.process')
            ->name('hr-integration.process-eligible');
        
        Route::post('/mark-eligible', [HrIntegrationController::class, 'markEligible'])
            ->middleware('permission:collections.hr-integration.mark')
            ->name('hr-integration.mark-eligible');
    });
});
