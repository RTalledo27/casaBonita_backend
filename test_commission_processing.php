<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Services\CommissionService;
use Carbon\Carbon;

echo "=== PROBANDO PROCESAMIENTO DE COMISIONES ===\n\n";

// Verificar datos antes del procesamiento
echo "Datos antes del procesamiento:\n";
$juneContracts = DB::table('contracts')
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2024)
    ->where('status', 'vigente')
    ->whereNotNull('advisor_id')
    ->whereNotNull('financing_amount')
    ->where('financing_amount', '>', 0)
    ->get(['contract_id', 'advisor_id', 'sign_date', 'financing_amount', 'term_months']);

echo "Contratos válidos en junio 2024: " . $juneContracts->count() . "\n";
foreach ($juneContracts as $contract) {
    echo "- Contrato {$contract->contract_id}: Asesor {$contract->advisor_id}, Monto: {$contract->financing_amount}, Plazo: {$contract->term_months} meses\n";
}
echo "\n";

// Verificar comisiones existentes
$existingCommissions = DB::table('commissions')
    ->where('period_month', 6)
    ->where('period_year', 2024)
    ->count();

echo "Comisiones existentes para junio 2024: {$existingCommissions}\n\n";

// Procesar comisiones
echo "Procesando comisiones para junio 2024...\n";

try {
    $commissionService = app(CommissionService::class);
    $result = $commissionService->processCommissionsForPeriod(6, 2024);
    
    echo "✅ Procesamiento exitoso!\n";
    echo "Comisiones procesadas: " . count($result) . "\n\n";
    
    // Mostrar detalles de las comisiones creadas
    foreach ($result as $index => $commission) {
        echo "Comisión " . ($index + 1) . ":\n";
        echo "- ID: {$commission->commission_id}\n";
        echo "- Empleado: {$commission->employee_id}\n";
        echo "- Contrato: {$commission->contract_id}\n";
        echo "- Monto: {$commission->commission_amount}\n";
        echo "- Porcentaje: {$commission->commission_percentage}%\n";
        echo "- Estado: {$commission->status}\n";
        echo "- Es pagable: " . ($commission->is_payable ? 'Sí' : 'No') . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error en el procesamiento: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Verificar resultado final
echo "\nVerificación final:\n";
$finalCommissions = DB::table('commissions')
    ->where('period_month', 6)
    ->where('period_year', 2024)
    ->get(['commission_id', 'employee_id', 'contract_id', 'commission_amount', 'status', 'is_payable']);

echo "Total comisiones creadas: " . $finalCommissions->count() . "\n";

if ($finalCommissions->count() > 0) {
    echo "\n✅ ÉXITO: Las comisiones se están procesando correctamente\n";
    echo "El problema de reconocimiento de ventas ha sido solucionado\n";
} else {
    echo "\n❌ Aún hay problemas en el procesamiento\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";