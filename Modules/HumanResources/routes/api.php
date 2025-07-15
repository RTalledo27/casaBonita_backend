<?php

use Illuminate\Support\Facades\Route;
use Modules\HumanResources\Http\Controllers\CommissionController;
use Modules\HumanResources\Http\Controllers\EmployeeController;
use Modules\HumanResources\Http\Controllers\HumanResourcesController;
use Modules\HumanResources\Http\Controllers\PayrollController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('humanresources', HumanResourcesController::class)->names('humanresources');
});


Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum'])->prefix('hr')->group(
        function () {

            // Rutas de Empleados
            Route::prefix('employees')->group(function () {
                Route::get('/', [EmployeeController::class, 'index'])->name('hr.employees.index');
                Route::post('/', [EmployeeController::class, 'store'])->name('hr.employees.store');
                Route::get('/advisors', [EmployeeController::class, 'advisors'])->name('hr.employees.advisors');
                Route::get('/top-performers', [EmployeeController::class, 'topPerformers'])->name('hr.employees.top-performers');
                Route::get('/{employee}', [EmployeeController::class, 'show'])->name('hr.employees.show');
                Route::put('/{employee}', [EmployeeController::class, 'update'])->name('hr.employees.update');
                Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('hr.employees.destroy');
                Route::get('/{employee}/dashboard', [EmployeeController::class, 'dashboard'])->name('hr.employees.dashboard');
            });

            // Rutas de Comisiones
            Route::prefix('commissions')->group(function () {
                Route::get('/', [CommissionController::class, 'index'])->name('hr.commissions.index');
                Route::get('/{commission}', [CommissionController::class, 'show'])->name('hr.commissions.show');
                Route::post('/process-period', [CommissionController::class, 'processForPeriod'])->name('hr.commissions.process-period');
                Route::post('/pay', [CommissionController::class, 'pay'])->name('hr.commissions.pay');
            });

            // Rutas de Nómina
            Route::prefix('payroll')->group(function () {
                Route::get('/', [PayrollController::class, 'index'])->name('hr.payroll.index');
                Route::get('/{payroll}', [PayrollController::class, 'show'])->name('hr.payroll.show');
                Route::post('/generate', [PayrollController::class, 'generate'])->name('hr.payroll.generate');
                Route::post('/{payroll}/process', [PayrollController::class, 'process'])->name('hr.payroll.process');
                Route::post('/{payroll}/approve', [PayrollController::class, 'approve'])->name('hr.payroll.approve');
            });
        }
    );
});
