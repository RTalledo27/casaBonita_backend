<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;

$employeeId = 1;
$month = 7;
$year = 2025;

echo "=== VERIFICACIÓN DE CONTRATOS PARA EMPLEADO ID {$employeeId} EN {$month}/{$year} ===\n";

// Verificar si el empleado existe
$employee = Employee::find($employeeId);
if (!$employee) {
    echo "ERROR: Empleado con ID {$employeeId} no encontrado\n";
    exit(1);
}

echo "Empleado: {$employee->first_name} {$employee->last_name}\n";
echo "Tipo: {$employee->employee_type}\n";
echo "Estado: {$employee->employment_status}\n\n";

// Verificar contratos en el período específico
$contracts = Contract::where('advisor_id', $employeeId)
    ->whereMonth('sign_date', $month)
    ->whereYear('sign_date', $year)
    ->get();

echo "Total de contratos encontrados: " . $contracts->count() . "\n\n";

if ($contracts->count() > 0) {
    echo "CONTRATOS ENCONTRADOS:\n";
    foreach ($contracts as $contract) {
        echo "- ID: {$contract->contract_id}\n";
        echo "  Número: {$contract->contract_number}\n";
        echo "  Fecha firma: {$contract->sign_date}\n";
        echo "  Estado: {$contract->status}\n";
        echo "  Valor: {$contract->total_value}\n";
        echo "  Asesor ID: {$contract->advisor_id}\n\n";
    }
} else {
    echo "NO SE ENCONTRARON CONTRATOS para el empleado {$employeeId} en {$month}/{$year}\n";
    echo "Esto explica por qué se obtiene 'Comisión no encontrada'\n\n";
    
    // Verificar si hay contratos en otros períodos
    $otherContracts = Contract::where('advisor_id', $employeeId)->get();
    echo "Contratos del empleado en otros períodos: " . $otherContracts->count() . "\n";
    
    if ($otherContracts->count() > 0) {
        echo "PERÍODOS CON CONTRATOS:\n";
        foreach ($otherContracts as $contract) {
            $contractDate = \Carbon\Carbon::parse($contract->sign_date);
            echo "- {$contractDate->format('m/Y')} (Contrato: {$contract->contract_number})\n";
        }
    }
}

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";