<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Services\EmployeeService;

echo "=== DEBUG COMISIONES PENDIENTES ===\n";

// Parámetros para julio 2025
$month = 7;
$year = 2025;

echo "Verificando comisiones para: $month/$year\n\n";

// Obtener todas las comisiones del período
$commissions = Commission::where('period_month', $month)
    ->where('period_year', $year)
    ->get();

echo "Total de comisiones encontradas: " . $commissions->count() . "\n\n";

// Agrupar por estado
$byStatus = $commissions->groupBy('payment_status');

echo "=== COMISIONES POR ESTADO ===\n";
foreach ($byStatus as $status => $statusCommissions) {
    $count = $statusCommissions->count();
    $totalAmount = $statusCommissions->sum('commission_amount');
    
    echo "Estado: $status\n";
    echo "  Cantidad: $count\n";
    echo "  Monto total: $" . number_format($totalAmount, 2) . "\n\n";
}

// Verificar específicamente las pendientes
echo "=== DETALLE DE COMISIONES PENDIENTES ===\n";
$pendingCommissions = $commissions->where('payment_status', 'pendiente');

echo "Comisiones pendientes: " . $pendingCommissions->count() . "\n";

foreach ($pendingCommissions as $commission) {
    echo "ID: {$commission->commission_id}, Empleado: {$commission->employee_id}, Monto: $" . number_format($commission->commission_amount, 2) . "\n";
}

$totalPending = $pendingCommissions->sum('commission_amount');
echo "\nTOTAL PENDIENTES: $" . number_format($totalPending, 2) . "\n\n";

// Verificar qué devuelve el servicio de empleados
echo "=== VERIFICANDO SERVICIO DE EMPLEADOS ===\n";

try {
    $employeeService = new EmployeeService();
    $dashboardData = $employeeService->getAdminDashboard($month, $year);
    
    echo "Datos del dashboard:\n";
    echo "Commissions summary:\n";
    if (isset($dashboardData['commissions_summary'])) {
        $commSummary = $dashboardData['commissions_summary'];
        echo "  Total amount: " . ($commSummary['total_amount'] ?? 'N/A') . "\n";
        echo "  Count: " . ($commSummary['count'] ?? 'N/A') . "\n";
        
        if (isset($commSummary['by_status'])) {
            echo "  By status:\n";
            foreach ($commSummary['by_status'] as $status => $data) {
                echo "    $status: count=" . ($data['count'] ?? 'N/A') . ", total_amount=" . ($data['total_amount'] ?? 'N/A') . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error al obtener datos del dashboard: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEBUG ===\n";