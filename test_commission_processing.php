<?php

require_once 'vendor/autoload.php';

// Configurar Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Commission;

echo "=== PROBANDO PROCESAMIENTO DE COMISIONES ===\n";

// 1. Verificar contratos con asesores
$contractsWithAdvisors = Contract::whereNotNull('advisor_id')
    ->where('advisor_id', '!=', '')
    ->where('financing_amount', '>', 0)
    ->get();

echo "Contratos con asesores y financiamiento: " . $contractsWithAdvisors->count() . "\n\n";

foreach ($contractsWithAdvisors->take(5) as $contract) {
    echo "Contrato {$contract->contract_id}: Asesor {$contract->advisor_id}, Financiamiento: {$contract->financing_amount}\n";
}

echo "\n=== PROCESANDO COMISIONES ===\n";

// 2. Procesar comisiones usando el contenedor de Laravel
$commissionRepo = $app->make(CommissionRepository::class);
$employeeRepo = $app->make(EmployeeRepository::class);
$commissionService = new CommissionService($commissionRepo, $employeeRepo);

$currentMonth = date('n');
$currentYear = date('Y');

echo "Procesando comisiones para: {$currentMonth}/{$currentYear}\n\n";

try {
    $result = $commissionService->processCommissionsForPeriod($currentMonth, $currentYear);
    
    echo "Resultado del procesamiento:\n";
    echo "- Cantidad procesada: " . count($result) . "\n";
    
    if (count($result) > 0) {
        echo "\nPrimeras comisiones procesadas:\n";
        foreach (array_slice($result, 0, 3) as $commission) {
            echo "- Comisión ID: {$commission->commission_id}, Empleado: {$commission->employee_id}, Monto: {$commission->commission_amount}\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== VERIFICANDO COMISIONES CREADAS ===\n";

// 3. Verificar comisiones en la base de datos
$commissions = Commission::whereMonth('period_month', $currentMonth)
    ->whereYear('period_year', $currentYear)
    ->with('employee.user')
    ->get();

echo "Comisiones encontradas en BD: " . $commissions->count() . "\n\n";

foreach ($commissions as $commission) {
    $employeeName = $commission->employee && $commission->employee->user 
        ? $commission->employee->user->first_name . ' ' . $commission->employee->user->last_name
        : 'N/A';
    
    echo "Comisión ID: {$commission->commission_id}\n";
    echo "- Empleado: {$employeeName} (ID: {$commission->employee_id})\n";
    echo "- Contrato: {$commission->contract_id}\n";
    echo "- Monto: {$commission->commission_amount}\n";
    echo "- Período: {$commission->period_month}/{$commission->period_year}\n";
    echo "- Estado: {$commission->status}\n\n";
}

echo "=== PROCESO COMPLETADO ===\n";