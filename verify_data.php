<?php

use Illuminate\Foundation\Application;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;

echo "=== VERIFICACIÓN DE DATOS ===\n";

$currentMonth = date('n');
$currentYear = date('Y');

echo "Mes actual: $currentMonth/$currentYear\n\n";

// Verificar contratos vigentes con asesor
$contracts = Contract::where('status', 'vigente')
    ->whereNotNull('advisor_id')
    ->get();

echo "Contratos vigentes con asesor: " . $contracts->count() . "\n";

foreach ($contracts as $contract) {
    echo "- Contrato: {$contract->contract_number} | Asesor ID: {$contract->advisor_id} | Fecha: {$contract->sign_date} | Monto: {$contract->total_price}\n";
}

echo "\n=== CONTRATOS DEL MES ACTUAL ===\n";

$currentMonthContracts = Contract::where('status', 'vigente')
    ->whereNotNull('advisor_id')
    ->whereMonth('sign_date', $currentMonth)
    ->whereYear('sign_date', $currentYear)
    ->get();

echo "Contratos del mes actual: " . $currentMonthContracts->count() . "\n";

foreach ($currentMonthContracts as $contract) {
    echo "- Contrato: {$contract->contract_number} | Asesor ID: {$contract->advisor_id} | Fecha: {$contract->sign_date} | Monto: {$contract->total_price}\n";
}

echo "\n=== EMPLEADOS ===\n";
$employees = Employee::all();
echo "Total empleados: " . $employees->count() . "\n";

foreach ($employees as $employee) {
    echo "- ID: {$employee->employee_id} | Nombre: {$employee->name} | Posición: {$employee->position}\n";
}

echo "\n=== VERIFICACIÓN COMPLETA ===\n";