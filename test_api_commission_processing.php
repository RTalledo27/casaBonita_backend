<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Illuminate\Support\Facades\DB;

echo "=== PROBANDO API DE PROCESAMIENTO DE COMISIONES ===\n\n";

// Simular la llamada que hace el frontend
echo "Simulando llamada del frontend: POST /api/v1/hr/commissions/process-period\n";
echo "Parámetros: {year: 2025, month: 6}\n\n";

// Verificar datos antes del procesamiento
echo "Verificando datos antes del procesamiento:\n";
$june2025Contracts = DB::table('contracts')
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2025)
    ->where('status', 'vigente')
    ->whereNotNull('advisor_id')
    ->whereNotNull('financing_amount')
    ->where('financing_amount', '>', 0)
    ->get(['contract_id', 'advisor_id', 'sign_date', 'financing_amount', 'term_months']);

echo "Contratos válidos encontrados: " . $june2025Contracts->count() . "\n";
foreach ($june2025Contracts as $contract) {
    echo "- Contrato {$contract->contract_id}: Asesor {$contract->advisor_id}, Monto: {$contract->financing_amount}\n";
}
echo "\n";

// Verificar comisiones existentes
$existingCommissions = DB::table('commissions')
    ->where('period_month', 6)
    ->where('period_year', 2025)
    ->count();

echo "Comisiones existentes para junio 2025: {$existingCommissions}\n\n";

// Procesar comisiones usando el servicio
echo "Procesando comisiones...\n";

try {
    $commissionService = app(CommissionService::class);
    $result = $commissionService->processCommissionsForPeriod(6, 2025);
    
    echo "✅ Procesamiento exitoso!\n";
    echo "Comisiones procesadas: " . count($result) . "\n\n";
    
    // Simular la respuesta de la API
    $apiResponse = [
        'success' => true,
        'data' => $result,
        'message' => 'Comisiones procesadas exitosamente',
        'count' => count($result)
    ];
    
    echo "Respuesta de la API:\n";
    echo json_encode($apiResponse, JSON_PRETTY_PRINT) . "\n\n";
    
    // Mostrar detalles de algunas comisiones
    if (count($result) > 0) {
        echo "Detalles de las primeras 3 comisiones:\n";
        for ($i = 0; $i < min(3, count($result)); $i++) {
            $commission = $result[$i];
            echo "Comisión " . ($i + 1) . ":\n";
            echo "- ID: {$commission->commission_id}\n";
            echo "- Empleado: {$commission->employee_id}\n";
            echo "- Contrato: {$commission->contract_id}\n";
            echo "- Monto: {$commission->commission_amount}\n";
            echo "- Estado: {$commission->status}\n";
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error en el procesamiento: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    // Simular respuesta de error de la API
    $apiResponse = [
        'success' => false,
        'data' => [],
        'message' => 'Error al procesar comisiones: ' . $e->getMessage(),
        'count' => 0
    ];
    
    echo "\nRespuesta de error de la API:\n";
    echo json_encode($apiResponse, JSON_PRETTY_PRINT) . "\n";
}

// Verificar resultado final
echo "\nVerificación final:\n";
$finalCommissions = DB::table('commissions')
    ->where('period_month', 6)
    ->where('period_year', 2025)
    ->count();

echo "Total comisiones en la base de datos: {$finalCommissions}\n";

if ($finalCommissions > 0) {
    echo "\n✅ ÉXITO: La API debería devolver datos correctamente al frontend\n";
} else {
    echo "\n❌ Problema: No se crearon comisiones\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";