<?php

use Illuminate\Support\Facades\Route;
use Modules\HumanResources\Http\Controllers\BonusController;
use Modules\HumanResources\Http\Controllers\BonusGoalController;
use Modules\HumanResources\Http\Controllers\BonusTypeController;
use Modules\HumanResources\Http\Controllers\CommissionController;
use Modules\HumanResources\Http\Controllers\EmployeeController;
use Modules\HumanResources\Http\Controllers\HumanResourcesController;
use Modules\HumanResources\Http\Controllers\PayrollController;
use Modules\HumanResources\Http\Controllers\TeamController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('humanresources', HumanResourcesController::class)->names('humanresources');
});


Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum'])->prefix('hr')->group(
        function () {

            // Rutas de Empleados
            Route::prefix('employees')->group(function () {
                //funcionando
                Route::get('/', [EmployeeController::class, 'index'])->name('hr.employees.index');
                Route::get('admin-dashboard', [EmployeeController::class, 'adminDashboard']);
                Route::get('advisors', [EmployeeController::class, 'advisors'])->name('hr.employees.advisors');
                Route::get('/{employee}', [EmployeeController::class,'show'])->name('hr.employees.show');
                Route::post('/', [EmployeeController::class, 'store'])->name('hr.employees.store');
                
                Route::get('{employee}/dashboard', [EmployeeController::class, 'dashboard']);
                Route::apiResource('/', EmployeeController::class)->names('hr.employees');
            });

            // Rutas de Comisiones
            Route::prefix('commissions')->group(function () {
                Route::get('/', [CommissionController::class, 'index'])->name('hr.commissions.index');
                Route::get('/sales-detail', [CommissionController::class, 'getSalesDetail'])->name('hr.commissions.sales-detail');

                Route::get('/{commission}', [CommissionController::class, 'show'])->name('hr.commissions.show');
                Route::post('/process-period', [CommissionController::class, 'processForPeriod'])->name('hr.commissions.process-period');
                Route::post('/pay', [CommissionController::class, 'pay'])->name('hr.commissions.pay');
            });

            // Bonus routes
            Route::prefix('bonuses')->group(function () {
                Route::get('/', [BonusController::class, 'index'])->name('hr.bonuses.index');
                Route::get('/{id}', [BonusController::class, 'show'])->name('hr.bonuses.show');
                Route::post('/', [BonusController::class, 'store'])->name('hr.bonuses.store');
                Route::post('/process-automatic', [BonusController::class, 'processAutomaticBonuses'])->name('hr.bonuses.process-automatic');

                Route::get('/', [BonusController::class, 'index'])->name('hr.bonuses.index');
                Route::get('/{id}', [BonusController::class, 'show'])->name('hr.bonuses.show');
                Route::post('/', [BonusController::class, 'store'])->name('hr.bonuses.store');
                Route::post('/process-automatic', [BonusController::class, 'processAutomaticBonuses'])->name('hr.bonuses.process-automatic');
                Route::get('/dashboard', [BonusController::class, 'dashboardBonuses'])->name('hr.bonuses.dashboard');

            });

            // Rutas de Nómina
            Route::prefix('payroll')->group(function () {
                Route::get('/', [PayrollController::class, 'index'])->name('hr.payroll.index');
                Route::get('/{payroll}', [PayrollController::class, 'show'])->name('hr.payroll.show');
                Route::post('/generate', [PayrollController::class, 'generate'])->name('hr.payroll.generate');
                Route::post('/process-bulk', [PayrollController::class, 'processBulk'])->name('hr.payroll.process-bulk');
                Route::post('/{payroll}/process', [PayrollController::class, 'process'])->name('hr.payroll.process');
                Route::post('/{payroll}/approve', [PayrollController::class, 'approve'])->name('hr.payroll.approve');
            });

            // BonusType routes
            Route::prefix('bonus-types')->group(function () {
                Route::get('/active', [BonusTypeController::class, 'active'])->name('hr.bonus-types.active');
                Route::get('/', [BonusTypeController::class, 'index'])->name('hr.bonus-types.index');
                Route::get('/{id}', [BonusTypeController::class, 'show'])->name('hr.bonus-types.show');
                Route::post('/', [BonusTypeController::class, 'store'])->name('hr.bonus-types.store');
                Route::put('/{id}', [BonusTypeController::class, 'update'])->name('hr.bonus-types.update');
                Route::delete('/{id}', [BonusTypeController::class, 'destroy'])->name('hr.bonus-types.destroy');
            });

            // BonusGoal routes
            Route::prefix('bonus-goals')->group(function () {
                Route::get('/', [BonusGoalController::class, 'index'])->name('hr.bonus-goals.index');
                Route::get('/{id}', [BonusGoalController::class, 'show'])->name('hr.bonus-goals.show');
                Route::post('/', [BonusGoalController::class, 'store'])->name('hr.bonus-goals.store');
                Route::put('/{id}', [BonusGoalController::class, 'update'])->name('hr.bonus-goals.update');
                Route::delete('/{id}', [BonusGoalController::class, 'destroy'])->name('hr.bonus-goals.destroy');
            });

            // Teams routes
            Route::prefix('teams')->group(function () {
                Route::get('/', [TeamController::class, 'index'])->name('hr.teams.index');
                Route::get('/{id}', [TeamController::class, 'show'])->name('hr.teams.show');
                Route::post('/', [TeamController::class, 'store'])->name('hr.teams.store');
                Route::put('/{id}', [TeamController::class, 'update'])->name('hr.teams.update');
                Route::delete('/{id}', [TeamController::class, 'destroy'])->name('hr.teams.destroy');
                Route::get('/{id}/members', [TeamController::class, 'members'])->name('hr.teams.members');
                Route::patch('/{id}/assign-leader', [TeamController::class, 'assignLeader'])->name('hr.teams.assign-leader');
                Route::patch('/{id}/toggle-status', [TeamController::class, 'toggleStatus'])->name('hr.teams.toggle-status');
            });
        }
    );
});
