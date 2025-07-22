<?php

use Illuminate\Foundation\Application;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Models\Employee;

echo "=== PRUEBA DEL DASHBOARD API ===\n";

$commissionService = $app->make(CommissionService::class);
$currentMonth = date('n');
$currentYear = date('Y');

echo "Probando dashboard para empleado ID 1, mes $currentMonth/$currentYear\n\n";

try {
    $dashboard = $commissionService->getAdvisorDashboard(1, $currentMonth, $currentYear);
    
    echo "=== RESULTADO DEL DASHBOARD ===\n";
    echo "Empleado: {$dashboard['employee']['full_name']}\n";
    echo "Período: {$dashboard['period']['label']}\n\n";
    
    echo "=== RESUMEN DE VENTAS ===\n";
    echo "Cantidad de contratos: {$dashboard['sales_summary']['count']}\n";
    echo "Monto total: S/ " . number_format($dashboard['sales_summary']['total_amount'], 2) . "\n";
    echo "Meta: S/ " . number_format($dashboard['sales_summary']['goal'], 2) . "\n";
    echo "Porcentaje de logro: {$dashboard['sales_summary']['achievement_percentage']}%\n\n";
    
    echo "=== RESUMEN DE INGRESOS ===\n";
    echo "Salario base: S/ " . number_format($dashboard['earnings_summary']['base_salary'], 2) . "\n";
    echo "Comisiones: S/ " . number_format($dashboard['earnings_summary']['commissions'], 2) . "\n";
    echo "Bonos: S/ " . number_format($dashboard['earnings_summary']['bonuses'], 2) . "\n";
    echo "Total estimado: S/ " . number_format($dashboard['earnings_summary']['total_estimated'], 2) . "\n\n";
    
    echo "=== RENDIMIENTO ===\n";
    echo "Ranking: {$dashboard['performance']['ranking']} de {$dashboard['performance']['total_advisors']}\n\n";
    
    echo "=== CONTRATOS RECIENTES ===\n";
    foreach ($dashboard['recent_contracts'] as $contract) {
        echo "- {$contract['contract_number']}: S/ " . number_format($contract['total_price'], 2) . " ({$contract['sign_date']})\n";
    }
    
    echo "\n=== PRUEBA EXITOSA ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";