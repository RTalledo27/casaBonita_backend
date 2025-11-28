<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\ReportsController;
use Modules\Reports\Http\Controllers\SalesReportsController;
use Modules\Reports\Http\Controllers\PaymentSchedulesController;
use Modules\Reports\Http\Controllers\ProjectionsController;
use Modules\Reports\Http\Controllers\ProjectionController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::prefix('v1')->group(function () {
    // Main Reports Controller
    Route::prefix('reports')->group(function () {
        Route::get('/types', [ReportsController::class, 'getReportTypes']);
        Route::post('/export', [ReportsController::class, 'export']);
        Route::get('/status/{reportId}', [ReportsController::class, 'getReportStatus']);
        Route::get('/download/{reportId}', [ReportsController::class, 'download']);
        Route::get('/history', [ReportsController::class, 'getReportsHistory']);
        
        // Specific Excel Export Routes (Sales)
        Route::get('/export/monthly-income', [SalesReportsController::class, 'exportMonthlyIncome']);
        Route::get('/export/detailed-sales', [SalesReportsController::class, 'exportDetailedSales']);
        Route::get('/export/client-details', [SalesReportsController::class, 'exportClientDetails']);
    });

    // Sales Reports
    Route::prefix('reports/sales')->group(function () {
        Route::get('/all', [SalesReportsController::class, 'getAllSales']);
        Route::get('/dashboard', [SalesReportsController::class, 'getDashboard']);
        Route::get('/by-period', [SalesReportsController::class, 'getSalesByPeriod']);
        Route::get('/performance', [SalesReportsController::class, 'getSalesPerformance']);
        Route::get('/conversion-funnel', [SalesReportsController::class, 'getConversionFunnel']);
        Route::get('/top-products', [SalesReportsController::class, 'getTopProducts']);
        Route::post('/export', [SalesReportsController::class, 'exportToExcel']);
    });

    // Payment Schedules Reports
    Route::prefix('reports/payment-schedules')->group(function () {
        Route::get('/overview', [PaymentSchedulesController::class, 'getOverview']);
        Route::get('/by-status', [PaymentSchedulesController::class, 'getByStatus']);
        Route::get('/overdue', [PaymentSchedulesController::class, 'getOverdue']);
        Route::get('/trends', [PaymentSchedulesController::class, 'getPaymentTrends']);
        Route::get('/collection-efficiency', [PaymentSchedulesController::class, 'getCollectionEfficiency']);
        Route::get('/upcoming', [PaymentSchedulesController::class, 'getUpcoming']);
    });

    // Projections Reports
    Route::prefix('reports/projections')->group(function () {
        // Old endpoints (keep for backwards compatibility)
        Route::get('/sales', [ProjectionsController::class, 'getSalesProjections']);
        Route::get('/cash-flow', [ProjectionsController::class, 'getCashFlowProjections']);
        Route::get('/inventory', [ProjectionsController::class, 'getInventoryProjections']);
        Route::get('/market-analysis', [ProjectionsController::class, 'getMarketAnalysis']);
        Route::get('/roi', [ProjectionsController::class, 'getROIProjections']);
        Route::get('/scenario-analysis', [ProjectionsController::class, 'getScenarioAnalysis']);
        
        // Revenue Projection with Linear Regression
        Route::get('/revenue', [ProjectionController::class, 'getRevenueProjection']);
        Route::get('/revenue/summary', [ProjectionController::class, 'getProjectionSummary']);
        Route::get('/revenue/compare', [ProjectionController::class, 'getQuarterComparison']);
    });

    // Projected Reports (New comprehensive endpoint)
    Route::prefix('reports/projected')->group(function () {
        Route::get('/', [\Modules\Reports\app\Http\Controllers\ProjectedReportController::class, 'index']);
        Route::get('/metrics', [\Modules\Reports\app\Http\Controllers\ProjectedReportController::class, 'getMetrics']);
        Route::get('/charts/revenue', [\Modules\Reports\app\Http\Controllers\ProjectedReportController::class, 'getRevenueProjectionChart']);
        Route::get('/charts/sales', [\Modules\Reports\app\Http\Controllers\ProjectedReportController::class, 'getSalesProjectionChart']);
        Route::get('/charts/cashflow', [\Modules\Reports\app\Http\Controllers\ProjectedReportController::class, 'getCashFlowChart']);
        Route::post('/export', [\Modules\Reports\app\Http\Controllers\ProjectedReportController::class, 'exportToExcel']);
        Route::post('/export-payment-schedule', [\Modules\Reports\app\Http\Controllers\ProjectedReportController::class, 'exportPaymentScheduleProjection']);
        Route::get('/{id}', [\Modules\Reports\app\Http\Controllers\ProjectedReportController::class, 'show']);
    });
});
