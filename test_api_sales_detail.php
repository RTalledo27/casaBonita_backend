<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Modules\HumanResources\Http\Controllers\CommissionController;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;

echo "=== PRUEBA DEL ENDPOINT API DE DETALLE DE VENTAS ===\n\n";

// Simular una petición HTTP
$request = new Request([
    'employee_id' => 1,
    'month' => date('n'),
    'year' => date('Y')
]);

echo "📋 Parámetros de la petición:\n";
echo "- employee_id: {$request->employee_id}\n";
echo "- month: {$request->month}\n";
echo "- year: {$request->year}\n\n";

try {
    // Inicializar dependencias
    $commissionModel = new Commission();
    $commissionRepo = new CommissionRepository($commissionModel);
    $employeeModel = new Employee();
    $employeeRepo = new EmployeeRepository($employeeModel);
    $commissionService = new CommissionService($commissionRepo, $employeeRepo);
    
    // Crear controlador
    $controller = new CommissionController($commissionRepo, $commissionService);
    
    echo "=== EJECUTANDO ENDPOINT getSalesDetail ===\n";
    
    // Llamar al método del controlador
    $response = $controller->getSalesDetail($request);
    
    // Obtener el contenido de la respuesta
    $responseData = json_decode($response->getContent(), true);
    
    echo "✅ Respuesta del endpoint:\n";
    echo "Status Code: {$response->getStatusCode()}\n";
    echo "Success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
    echo "Message: {$responseData['message']}\n\n";
    
    if ($responseData['success'] && isset($responseData['data'])) {
        $data = $responseData['data'];
        
        echo "=== DATOS DEVUELTOS ===\n";
        echo "Empleado ID: {$data['employee_id']}\n";
        echo "Período: {$data['period']['month']}/{$data['period']['year']}\n";
        echo "Total de ventas: {$data['summary']['total_sales']}\n";
        echo "Comisión total: $" . number_format($data['summary']['total_commission_amount'], 2) . "\n";
        
        if ($data['summary']['total_sales'] > 0) {
            echo "Tasa promedio: " . number_format($data['summary']['average_commission_rate'], 2) . "%\n\n";
            
            echo "=== VENTAS INDIVIDUALES ===\n";
            foreach ($data['sales'] as $sale) {
                echo "\n--- Venta #{$sale['sale_number']} ---\n";
                echo "Contrato: {$sale['contract_number']}\n";
                echo "Cliente: {$sale['customer_name']}\n";
                echo "Monto: $" . number_format($sale['sale_amount'], 2) . "\n";
                echo "Plazo: {$sale['term_months']} meses\n";
                echo "Tasa: {$sale['commission_rate']}%\n";
                echo "Comisión: $" . number_format($sale['total_commission_amount'], 2) . "\n";
                
                if (!empty($sale['commissions'])) {
                    echo "Pagos divididos:\n";
                    foreach ($sale['commissions'] as $commission) {
                        echo "  - {$commission['payment_type']}: $" . number_format($commission['commission_amount'], 2) . "\n";
                    }
                }
            }
        } else {
            echo "\nℹ️  No hay ventas registradas para este asesor en el período especificado.\n";
        }
        
        echo "\n=== ESTRUCTURA DE RESPUESTA JSON ===\n";
        echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
    } else {
        echo "❌ Error en la respuesta: " . ($responseData['message'] ?? 'Error desconocido') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error al ejecutar el endpoint: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== INFORMACIÓN DEL ENDPOINT ===\n";
echo "URL: GET /api/v1/hr/commissions/sales-detail\n";
echo "Parámetros requeridos:\n";
echo "- employee_id (integer): ID del empleado/asesor\n";
echo "- month (integer): Mes (1-12)\n";
echo "- year (integer): Año (2020-2030)\n\n";

echo "Ejemplo de uso con cURL:\n";
echo "curl -X GET 'http://localhost:8000/api/v1/hr/commissions/sales-detail?employee_id=1&month=" . date('n') . "&year=" . date('Y') . "' \\\n";
echo "     -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
echo "     -H 'Accept: application/json'\n\n";

echo "=== PRUEBA COMPLETADA ===\n";