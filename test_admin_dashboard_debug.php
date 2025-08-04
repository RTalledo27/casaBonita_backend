<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Services\BonusService;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Bonus;

echo "=== DEBUG ADMIN DASHBOARD ===\n";

$month = 7; // Julio 2025
$year = 2025;

echo "Período: $month/$year\n\n";

// Verificar empleados activos
echo "1. EMPLEADOS ACTIVOS:\n";
$employees = Employee::with(['user'])->active()->get();
echo "Total empleados activos: " . $employees->count() . "\n";

$advisors = Employee::with(['user'])->advisors()->active()->get();
echo "Total asesores activos: " . $advisors->count() . "\n\n";

// Verificar comisiones del período
echo "2. COMISIONES DEL PERÍODO:\n";
$commissions = Commission::where('period_month', $month)
    ->where('period_year', $year)
    ->with(['employee.user'])
    ->get();
echo "Total comisiones en $month/$year: " . $commissions->count() . "\n";

if ($commissions->count() > 0) {
    echo "Comisiones por empleado:\n";
    foreach ($commissions->groupBy('employee_id') as $employeeId => $empCommissions) {
        $employee = $empCommissions->first()->employee;
        $total = $empCommissions->sum('commission_amount');
        echo "  - {$employee->user->first_name} {$employee->user->last_name}: $" . number_format($total, 2) . "\n";
    }
}
echo "\n";

// Verificar bonos del período
echo "3. BONOS DEL PERÍODO:\n";
$bonuses = Bonus::where('period_month', $month)
    ->where('period_year', $year)
    ->with(['employee.user'])
    ->get();
echo "Total bonos en $month/$year: " . $bonuses->count() . "\n";

if ($bonuses->count() > 0) {
    echo "Bonos por empleado:\n";
    foreach ($bonuses->groupBy('employee_id') as $employeeId => $empBonuses) {
        $employee = $empBonuses->first()->employee;
        $total = $empBonuses->sum('bonus_amount');
        echo "  - {$employee->user->first_name} {$employee->user->last_name}: $" . number_format($total, 2) . "\n";
    }
}
echo "\n";

// Probar el método getTopPerformers
echo "4. TOP PERFORMERS:\n";
$employeeRepo = new EmployeeRepository(new Employee());
$topPerformers = $employeeRepo->getTopPerformers($month, $year, 10);
echo "Total top performers: " . $topPerformers->count() . "\n";

if ($topPerformers->count() > 0) {
    echo "Top performers:\n";
    foreach ($topPerformers as $index => $performer) {
        $commissionTotal = $performer->commissions->sum('commission_amount');
        $bonusTotal = $performer->bonuses->sum('bonus_amount');
        $total = $commissionTotal + $bonusTotal;
        echo "  " . ($index + 1) . ". {$performer->user->first_name} {$performer->user->last_name}:\n";
        echo "     Comisiones: $" . number_format($commissionTotal, 2) . "\n";
        echo "     Bonos: $" . number_format($bonusTotal, 2) . "\n";
        echo "     Total: $" . number_format($total, 2) . "\n";
    }
} else {
    echo "No hay top performers para este período.\n";
    echo "Verificando si hay asesores con datos:\n";
    
    foreach ($advisors as $advisor) {
        $advisorCommissions = Commission::where('employee_id', $advisor->employee_id)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->sum('commission_amount');
        
        $advisorBonuses = Bonus::where('employee_id', $advisor->employee_id)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->sum('bonus_amount');
        
        $total = $advisorCommissions + $advisorBonuses;
        
        if ($total > 0) {
            echo "  - {$advisor->user->first_name} {$advisor->user->last_name}: $" . number_format($total, 2) . "\n";
        }
    }
}
echo "\n";

// Probar el servicio completo
echo "5. SERVICIO COMPLETO:\n";
try {
    $commissionRepo = new CommissionRepository(new Commission());
    $commissionService = new CommissionService($commissionRepo, $employeeRepo);
    $bonusRepo = new \Modules\HumanResources\Repositories\BonusRepository(new \Modules\HumanResources\Models\Bonus());
    $bonusService = new BonusService($bonusRepo, $employeeRepo);
    
    $dashboard = $commissionService->getAdminDashboard($month, $year);
    $dashboard['bonuses'] = $bonusService->getBonusesForAdminDashboard($month, $year);
    
    echo "Dashboard generado exitosamente:\n";
    echo "  - Período: {$dashboard['period']['label']}\n";
    echo "  - Total comisiones: $" . number_format($dashboard['commissions_summary']['total_amount'], 2) . "\n";
    echo "  - Cantidad comisiones: {$dashboard['commissions_summary']['count']}\n";
    echo "  - Top performers: " . (is_array($dashboard['top_performers']) ? count($dashboard['top_performers']) : $dashboard['top_performers']->count()) . "\n";
    
    if (isset($dashboard['top_performers']) && (is_array($dashboard['top_performers']) ? count($dashboard['top_performers']) : $dashboard['top_performers']->count()) > 0) {
        echo "  Top performers data type: " . gettype($dashboard['top_performers']) . "\n";
        if (is_object($dashboard['top_performers'])) {
            echo "  Top performers class: " . get_class($dashboard['top_performers']) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error en el servicio: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEBUG ===\n";