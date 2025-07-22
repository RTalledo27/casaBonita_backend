<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Repositories\CommissionRepository;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\Customer;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Project;
use Carbon\Carbon;

echo "=== PRUEBA DEL ENDPOINT DE DETALLE DE VENTAS CON DATOS ===\n\n";

// Limpiar datos previos del asesor
echo "🧹 Limpiando datos previos...\n";
$advisorId = 1;
Contract::where('advisor_id', $advisorId)->delete();
Commission::where('employee_id', $advisorId)->delete();

// Obtener el asesor
$advisor = Employee::find($advisorId);
if (!$advisor) {
    echo "❌ No se encontró el asesor con ID {$advisorId}\n";
    exit(1);
}

echo "📋 Asesor: {$advisor->full_name} (ID: {$advisor->employee_id})\n";

// Configurar período actual
$currentMonth = date('n');
$currentYear = date('Y');
echo "📅 Período: {$currentMonth}/{$currentYear}\n\n";

// Crear datos de prueba (5 ventas con los montos del usuario)
$salesData = [
    ['amount' => 450000, 'term' => 48], // >36 meses
    ['amount' => 320000, 'term' => 24], // <36 meses
    ['amount' => 380000, 'term' => 60], // >36 meses
    ['amount' => 290000, 'term' => 18], // <36 meses
    ['amount' => 520000, 'term' => 72], // >36 meses
];

echo "=== CREANDO DATOS DE PRUEBA ===\n";

// Obtener un cliente y proyecto existente
$customer = Customer::first();
$project = Project::first();
$lot = Lot::where('project_id', $project->project_id)->where('status', 'disponible')->first();

if (!$customer || !$project || !$lot) {
    echo "❌ No se encontraron datos base necesarios (customer, project, lot)\n";
    exit(1);
}

foreach ($salesData as $index => $sale) {
    $contractNumber = 'TEST-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
    
    $contract = Contract::create([
        'contract_number' => $contractNumber,
        'customer_id' => $customer->customer_id,
        'advisor_id' => $advisorId,
        'lot_id' => $lot->lot_id,
        'total_price' => $sale['amount'],
        'financing_amount' => $sale['amount'],
        'term_months' => $sale['term'],
        'sign_date' => Carbon::now()->format('Y-m-d'),
        'status' => 'activo'
    ]);
    
    echo "✅ Contrato creado: {$contractNumber} - $" . number_format($sale['amount'], 2) . " ({$sale['term']} meses)\n";
}

echo "\n=== PROCESANDO COMISIONES ===\n";

// Inicializar servicio
$commissionModel = new Commission();
$commissionRepo = new CommissionRepository($commissionModel);
$employeeModel = new Employee();
$employeeRepo = new EmployeeRepository($employeeModel);
$commissionService = new CommissionService($commissionRepo, $employeeRepo);

// Procesar comisiones
$commissions = $commissionService->processCommissionsForPeriod($currentMonth, $currentYear);
echo "✅ Se generaron " . count($commissions) . " comisiones\n\n";

// Probar el nuevo endpoint
echo "=== PROBANDO ENDPOINT DE DETALLE DE VENTAS ===\n";

try {
    $salesDetail = $commissionService->getAdvisorSalesDetail(
        $advisorId,
        $currentMonth,
        $currentYear
    );

    echo "✅ Detalle obtenido exitosamente\n\n";

    // Mostrar resumen
    echo "=== RESUMEN ===\n";
    echo "Empleado ID: {$salesDetail['employee_id']}\n";
    echo "Período: {$salesDetail['period']['month']}/{$salesDetail['period']['year']}\n";
    echo "Total de ventas: {$salesDetail['summary']['total_sales']}\n";
    echo "Comisión total: $" . number_format($salesDetail['summary']['total_commission_amount'], 2) . "\n";
    echo "Tasa promedio: " . number_format($salesDetail['summary']['average_commission_rate'], 2) . "%\n\n";

    // Mostrar detalle de cada venta
    echo "=== DETALLE POR VENTA ===\n";
    foreach ($salesDetail['sales'] as $sale) {
        echo "\n--- Venta #{$sale['sale_number']} ---\n";
        echo "Contrato: {$sale['contract_number']}\n";
        echo "Cliente: {$sale['customer_name']}\n";
        echo "Proyecto: {$sale['project_name']}\n";
        echo "Lote: {$sale['lot_number']}\n";
        echo "Monto venta: $" . number_format($sale['sale_amount'], 2) . "\n";
        echo "Plazo: {$sale['term_months']} meses\n";
        echo "Tasa comisión: {$sale['commission_rate']}%\n";
        echo "Comisión total: $" . number_format($sale['total_commission_amount'], 2) . "\n";
        echo "Fecha firma: {$sale['sign_date']}\n";
        
        if (!empty($sale['commissions'])) {
            echo "Comisiones divididas:\n";
            foreach ($sale['commissions'] as $commission) {
                echo "  - {$commission['payment_type']}: $" . number_format($commission['commission_amount'], 2) . " ({$commission['payment_status']})\n";
            }
        } else {
            echo "⚠️  No se han generado comisiones para esta venta\n";
        }
    }

    // Verificar totales
    echo "\n=== VERIFICACIÓN DE TOTALES ===\n";
    $totalFromSales = array_sum(array_column($salesDetail['sales'], 'total_commission_amount'));
    echo "Total calculado desde ventas: $" . number_format($totalFromSales, 2) . "\n";
    echo "Total en resumen: $" . number_format($salesDetail['summary']['total_commission_amount'], 2) . "\n";
    echo "Diferencia: $" . number_format(abs($totalFromSales - $salesDetail['summary']['total_commission_amount']), 2) . "\n";

} catch (Exception $e) {
    echo "❌ Error al obtener detalle de ventas: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";