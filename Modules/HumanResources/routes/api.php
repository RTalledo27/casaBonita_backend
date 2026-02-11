<?php

use Illuminate\Support\Facades\Route;
use Modules\HumanResources\Http\Controllers\BonusController;
use Modules\HumanResources\Http\Controllers\BonusGoalController;
use Modules\HumanResources\Http\Controllers\BonusTypeController;
use Modules\HumanResources\app\Http\Controllers\CommissionController;
use Modules\HumanResources\Http\Controllers\CommissionPaymentVerificationController;
use Modules\HumanResources\Http\Controllers\EmployeeController;
use Modules\HumanResources\Http\Controllers\EmployeeImportController;
use Modules\HumanResources\Http\Controllers\HumanResourcesController;
use Modules\HumanResources\Http\Controllers\PayrollController;
use Modules\HumanResources\Http\Controllers\TeamController;
use Modules\HumanResources\Http\Controllers\TaxParameterController;
use Modules\HumanResources\Http\Controllers\OfficeController;
use Modules\HumanResources\Http\Controllers\AreaController;
use Modules\HumanResources\Http\Controllers\PositionController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('humanresources', HumanResourcesController::class)->names('humanresources');
});


// Debug routes sin autenticación
Route::post('/debug-commission/{commission}/pay-part', [CommissionController::class, 'debugPayPart']);
Route::post('/debug-commission/{commission}/set-approved', [CommissionController::class, 'debugSetApproved']);
Route::get('/debug-commission/list-testable', [CommissionController::class, 'debugListTestableCommissions']);
Route::get('/debug/commission-payment-verification/{commission}', [CommissionController::class, 'debugCommissionPaymentVerification']);

// Endpoint para debugging completo del proceso de pago de primera parte
    Route::post('debug-commission/{commission_id}/test-pay-part', [CommissionController::class, 'debugTestPayPart']);
    
    // Endpoint para buscar todas las comisiones disponibles para testing
    Route::post('debug-commission/find-testable', [CommissionController::class, 'debugTestPayPart']);
    
    // Endpoint para resetear una comisión a estado approved (solo para debugging)
    Route::post('debug-commission/{commission_id}/reset-to-approved', [CommissionController::class, 'debugResetToApproved']);
    
    // Endpoint para buscar comisiones en estado approved
    Route::get('debug/find-approved-commissions', [CommissionController::class, 'debugFindApprovedCommissions']);

Route::prefix('v1')->group(function () {
    
    Route::middleware(['auth:sanctum', 'check.password.change'])->prefix('hr')->group(
        function () {

            // Rutas de Empleados
            Route::prefix('employees')->group(function () {
                //funcionando
                Route::get('/', [EmployeeController::class, 'index'])->name('hr.employees.index');
                Route::get('admin-dashboard', [EmployeeController::class, 'adminDashboard']);
                Route::get('advisors', [EmployeeController::class, 'advisors'])->name('hr.employees.advisors');
                Route::get('/without-user', [EmployeeController::class, 'getEmployeesWithoutUser'])->name('hr.employees.without-user');
                Route::get('/with-commissions', [EmployeeController::class, 'withCommissions'])
                    ->middleware('permission:hr.employees.commissions.view')
                    ->name('hr.employees.with-commissions');
                Route::get('/{employee}', [EmployeeController::class,'show'])->name('hr.employees.show');
                Route::post('/', [EmployeeController::class, 'store'])->name('hr.employees.store');
                Route::post('/{employee}/generate-user', [EmployeeController::class, 'generateUser'])->name('hr.employees.generate-user');
                Route::post('/{employee}/notify-user-credentials', [EmployeeController::class, 'notifyUserCredentials'])->name('hr.employees.notify-user-credentials');
                
                Route::get('{employee}/dashboard', [EmployeeController::class, 'dashboard']);
            });

            // Rutas de Importación de Empleados
            Route::prefix('employee-import')->group(function () {
                Route::post('/validate', [EmployeeImportController::class, 'validateImport'])->name('hr.employee-import.validate');
                Route::post('/analyze', [EmployeeController::class, 'analyzeImport'])->name('hr.employee-import.analyze');
                Route::post('/import', [EmployeeImportController::class, 'import'])->name('hr.employee-import.import');
                Route::get('/template', [EmployeeImportController::class, 'downloadTemplate'])->name('hr.employee-import.template');
            });

            // Rutas de Comisiones
            Route::prefix('commissions')->group(function () {
                Route::get('/', [CommissionController::class, 'index'])->name('hr.commissions.index');
                Route::get('/sales-detail', [CommissionController::class, 'getSalesDetail'])->name('hr.commissions.sales-detail');
                Route::get('/by-commission-period', [CommissionController::class, 'getByCommissionPeriod'])->name('hr.commissions.by-commission-period');
                Route::get('/pending', [CommissionController::class, 'getPendingCommissions'])->name('hr.commissions.pending');
                Route::get('/{commission}', [CommissionController::class, 'show'])->name('hr.commissions.show');
                Route::get('/{commission}/split-summary', [CommissionController::class, 'getSplitPaymentSummary'])->name('hr.commissions.split-summary');
                
                Route::post('/process-period', [CommissionController::class, 'processForPeriod'])->name('hr.commissions.process-period');
                Route::post('/process-for-payroll', [CommissionController::class, 'processForPayroll'])->name('hr.commissions.process-for-payroll');
                Route::post('/pay', [CommissionController::class, 'pay'])->name('hr.commissions.pay');
                Route::post('/{commission}/pay-part', [CommissionController::class, 'payPart'])->name('hr.commissions.pay-part');
                Route::post('/mark-multiple-paid', [CommissionController::class, 'markMultipleAsPaid'])->name('hr.commissions.mark-multiple-paid');
                Route::post('/{commission}/split-payment', [CommissionController::class, 'createSplitPayment'])->name('hr.commissions.split-payment');
            });

            // Rutas de Verificaciones de Pago de Comisiones
            Route::prefix('commission-payment-verifications')->group(function () {
                Route::get('/requiring-verification', [CommissionPaymentVerificationController::class, 'getCommissionsRequiringVerification'])->name('hr.commission-verifications.requiring-verification');
                Route::get('/stats', [CommissionPaymentVerificationController::class, 'getVerificationStats'])->name('hr.commission-verifications.stats');
                Route::post('/verify-payment', [CommissionPaymentVerificationController::class, 'verifyPayment'])->name('hr.commission-verifications.verify-payment');
                Route::post('/process-automatic', [CommissionPaymentVerificationController::class, 'processAutomaticVerifications'])->name('hr.commission-verifications.process-automatic');
                Route::get('/{commission}/verifications', [CommissionPaymentVerificationController::class, 'index'])->name('hr.commission-verifications.index');
                Route::get('/{commission}/status', [CommissionPaymentVerificationController::class, 'getVerificationStatus'])->name('hr.commission-verifications.status');
                Route::post('/{verification}/reverse', [CommissionPaymentVerificationController::class, 'reverseVerification'])->name('hr.commission-verifications.reverse');
            });

            // Bonus routes
            Route::prefix('bonuses')->group(function () {
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

            // Offices routes
            Route::prefix('offices')->group(function () {
                Route::get('/', [OfficeController::class, 'index'])->name('hr.offices.index');
                Route::get('/{office}', [OfficeController::class, 'show'])->name('hr.offices.show');
                Route::post('/', [OfficeController::class, 'store'])->name('hr.offices.store');
                Route::put('/{office}', [OfficeController::class, 'update'])->name('hr.offices.update');
                Route::delete('/{office}', [OfficeController::class, 'destroy'])->name('hr.offices.destroy');
            });

            // Areas routes
            Route::prefix('areas')->group(function () {
                Route::get('/', [AreaController::class, 'index'])->name('hr.areas.index');
                Route::get('/{area}', [AreaController::class, 'show'])->name('hr.areas.show');
                Route::post('/', [AreaController::class, 'store'])->name('hr.areas.store');
                Route::put('/{area}', [AreaController::class, 'update'])->name('hr.areas.update');
                Route::delete('/{area}', [AreaController::class, 'destroy'])->name('hr.areas.destroy');
            });

            // Positions routes (Cargos)
            Route::prefix('positions')->group(function () {
                Route::get('/', [PositionController::class, 'index'])->name('hr.positions.index');
                Route::get('/{position}', [PositionController::class, 'show'])->name('hr.positions.show');
                Route::post('/', [PositionController::class, 'store'])->name('hr.positions.store');
                Route::put('/{position}', [PositionController::class, 'update'])->name('hr.positions.update');
                Route::delete('/{position}', [PositionController::class, 'destroy'])->name('hr.positions.destroy');
            });
            
            // Rutas de Parámetros Tributarios (Tax Parameters)
            Route::prefix('tax-parameters')->group(function () {
                Route::get('/', [TaxParameterController::class, 'index'])->name('hr.tax-parameters.index');
                Route::get('/current', [TaxParameterController::class, 'getCurrent'])->name('hr.tax-parameters.current');
                Route::get('/{year}', [TaxParameterController::class, 'getByYear'])->name('hr.tax-parameters.by-year');
                Route::post('/', [TaxParameterController::class, 'store'])->name('hr.tax-parameters.store');
                Route::put('/{year}', [TaxParameterController::class, 'update'])->name('hr.tax-parameters.update');
                Route::post('/copy-year', [TaxParameterController::class, 'copyYear'])->name('hr.tax-parameters.copy');
                Route::post('/calculate-family-allowance', [TaxParameterController::class, 'calculateFamilyAllowance'])->name('hr.tax-parameters.calc-family');
            });
        }
    );
});
