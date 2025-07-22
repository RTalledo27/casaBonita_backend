<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;

$advisor = Employee::where('employee_type', 'asesor_inmobiliario')->first();
$month = date('n');
$year = date('Y');

echo "=== VERIFICACIÓN DE CONTRATOS DEL ASESOR ===\n";
echo "Asesor: {$advisor->first_name} {$advisor->last_name} (ID: {$advisor->employee_id})\n";
echo "Período: {$month}/{$year}\n\n";

$totalContracts = Contract::where('advisor_id', $advisor->employee_id)
    ->whereMonth('sign_date', $month)
    ->whereYear('sign_date', $year)
    ->where('status', 'vigente')
    ->count();

$userTestContracts = Contract::where('advisor_id', $advisor->employee_id)
    ->where('contract_number', 'like', 'USER-TEST-%')
    ->count();

$otherTestContracts = Contract::where('advisor_id', $advisor->employee_id)
    ->where('contract_number', 'like', 'TEST-%')
    ->count();

echo "Total contratos del asesor en {$month}/{$year}: {$totalContracts}\n";
echo "Contratos de prueba del usuario (USER-TEST-): {$userTestContracts}\n";
echo "Otros contratos de prueba (TEST-): {$otherTestContracts}\n";
echo "Contratos regulares: " . ($totalContracts - $userTestContracts) . "\n\n";

echo "PROBLEMA IDENTIFICADO:\n";
echo "El asesor tiene {$totalContracts} contratos en total este mes.\n";
echo "Esto significa que cuando procesamos las 20 ventas del usuario,\n";
echo "el sistema está contando {$totalContracts} ventas en lugar de solo 20.\n";
echo "Por eso los porcentajes son más altos (3% y 4.2% para 20+ ventas\n";
echo "en lugar de los porcentajes correctos).\n\n";

echo "SOLUCIÓN: Limpiar TODOS los contratos del asesor antes de la prueba.\n";

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";