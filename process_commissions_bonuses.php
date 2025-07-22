<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Services\BonusService;
use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\Contract;

echo "=== PROCESANDO COMISIONES Y BONOS ===\n";

$currentMonth = date('n');
$currentYear = date('Y');

echo "Mes actual: $currentMonth\n";
echo "Año actual: $currentYear\n\n";

// Obtener el servicio de comisiones
$commissionService = $app->make(CommissionService::class);

// Procesar comisiones para el período actual
echo "=== PROCESANDO COMISIONES ===\n";
$commissions = $commissionService->processCommissionsForPeriod($currentMonth, $currentYear);

echo "Comisiones procesadas: " . count($commissions) . "\n";
foreach ($commissions as $commission) {
    echo "- Empleado ID: {$commission->employee_id}, Contrato: {$commission->contract_id}, Monto: S/ {$commission->commission_amount}\n";
}

// Obtener el servicio de bonos
$bonusService = $app->make(BonusService::class);

// Procesar bonos para el período actual
echo "\n=== PROCESANDO BONOS ===\n";
$bonuses = $bonusService->processBonusesForPeriod($currentMonth, $currentYear);

echo "Bonos procesados: " . count($bonuses) . "\n";
foreach ($bonuses as $bonus) {
    echo "- Empleado ID: {$bonus->employee_id}, Tipo: {$bonus->bonus_name}, Monto: S/ {$bonus->bonus_amount}\n";
}

// Verificar los totales para el empleado 1
echo "\n=== VERIFICACIÓN FINAL ===\n";
$employee = Employee::find(1);
if ($employee) {
    $monthlyCommissions = $employee->calculateMonthlyCommissions($currentMonth, $currentYear);
    $monthlyBonuses = $employee->calculateMonthlyBonuses($currentMonth, $currentYear);
    
    echo "Empleado: {$employee->full_name}\n";
    echo "Comisiones del mes: S/ $monthlyCommissions\n";
    echo "Bonos del mes: S/ $monthlyBonuses\n";
    echo "Total ingresos estimados: S/ " . ($employee->base_salary + $monthlyCommissions + $monthlyBonuses) . "\n";
}

echo "\n=== PROCESO COMPLETADO ===\n";