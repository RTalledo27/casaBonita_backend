<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Models\Employee;

echo "=== PROBANDO ENDPOINT SALES-DETAIL ===\n";

// Obtener un empleado válido
$employee = Employee::first();
if (!$employee) {
    echo "❌ No hay empleados en la base de datos\n";
    exit(1);
}

echo "Probando con empleado ID: {$employee->employee_id} ({$employee->employee_code})\n";
echo "Mes: 12, Año: 2024\n\n";

try {
    $commissionService = app(CommissionService::class);
    
    $salesDetail = $commissionService->getAdvisorSalesDetail(
        $employee->employee_id,
        12, // Diciembre
        2024
    );
    
    echo "✅ Endpoint funciona correctamente\n";
    echo "Respuesta:\n";
    echo json_encode($salesDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error en el endpoint: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== PROBANDO CON EMPLEADO INEXISTENTE ===\n";

try {
    $commissionService = app(CommissionService::class);
    
    $salesDetail = $commissionService->getAdvisorSalesDetail(
        999, // ID inexistente
        12,
        2024
    );
    
    echo "Respuesta con ID inexistente:\n";
    echo json_encode($salesDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error esperado con ID inexistente: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE PRUEBAS ===\n";