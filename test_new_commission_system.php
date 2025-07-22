<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Carbon\Carbon;

echo "=== PRUEBA DEL NUEVO SISTEMA DE COMISIONES ===\n\n";

// Crear servicio de comisiones
$commissionService = app(CommissionService::class);

// Función para mostrar resultados de comisiones
function showCommissionResults($commissions, $title) {
    echo "\n--- {$title} ---\n";
    if (empty($commissions)) {
        echo "No se generaron comisiones.\n";
        return;
    }
    
    foreach ($commissions as $commission) {
        echo "Comisión ID: {$commission->commission_id}\n";
        echo "Empleado: {$commission->employee_id}\n";
        echo "Monto: $" . number_format($commission->commission_amount, 2) . "\n";
        echo "Porcentaje: {$commission->commission_percentage}%\n";
        echo "Período: {$commission->period_month}/{$commission->period_year}\n";
        echo "Notas: {$commission->notes}\n";
        echo "---\n";
    }
}

// Función para crear contrato de prueba
function createTestContract($advisorId, $financingAmount, $termMonths, $signDate) {
    return Contract::create([
        'contract_number' => 'TEST-' . uniqid(),
        'reservation_id' => 1, // Asumiendo que existe
        'advisor_id' => $advisorId,
        'sign_date' => $signDate,
        'total_price' => $financingAmount + 10000, // Enganche de 10k
        'down_payment' => 10000,
        'financing_amount' => $financingAmount,
        'interest_rate' => 0.12,
        'term_months' => $termMonths,
        'monthly_payment' => 1000,
        'currency' => 'USD',
        'status' => 'vigente'
    ]);
}

// Obtener un asesor de prueba
$advisor = Employee::where('employee_type', 'asesor_inmobiliario')->first();
if (!$advisor) {
    echo "Error: No se encontró ningún asesor inmobiliario.\n";
    exit(1);
}

echo "Asesor de prueba: {$advisor->first_name} {$advisor->last_name} (ID: {$advisor->employee_id})\n\n";

// Limpiar comisiones anteriores del asesor para este mes
$currentMonth = date('n');
$currentYear = date('Y');
Commission::where('employee_id', $advisor->employee_id)
    ->where('period_month', $currentMonth)
    ->where('period_year', $currentYear)
    ->delete();

// Limpiar contratos de prueba anteriores
Contract::where('advisor_id', $advisor->employee_id)
    ->where('contract_number', 'like', 'TEST-%')
    ->delete();

echo "Datos de prueba limpiados.\n\n";

// ESCENARIO CON DATOS REALES: 20 ventas con montos y términos específicos
echo "=== ESCENARIO CON DATOS REALES: 20 VENTAS (PAGO DIVIDIDO 70/30) ===\n";

// Datos específicos proporcionados por el usuario
$salesData = [
    ['amount' => 19140.00, 'term' => 48],   // >36 meses
    ['amount' => 20097.00, 'term' => 48],   // >36 meses
    ['amount' => 31046.40, 'term' => 24],   // <36 meses
    ['amount' => 18480.00, 'term' => 48],   // >36 meses
    ['amount' => 18480.00, 'term' => 48],   // >36 meses
    ['amount' => 18480.00, 'term' => 48],   // >36 meses
    ['amount' => 18480.00, 'term' => 48],   // >36 meses
    ['amount' => 18480.00, 'term' => 48],   // >36 meses
    ['amount' => 18480.00, 'term' => 48],   // >36 meses
    ['amount' => 18480.00, 'term' => 48],   // >36 meses
    ['amount' => 24696.00, 'term' => 24],   // <36 meses
    ['amount' => 23284.80, 'term' => 48],   // >36 meses
    ['amount' => 24901.80, 'term' => 24],   // <36 meses
    ['amount' => 34151.04, 'term' => 24],   // <36 meses
    ['amount' => 27165.60, 'term' => 24],   // <36 meses
    ['amount' => 21168.00, 'term' => 24],   // <36 meses
    ['amount' => 20160.00, 'term' => 48],   // >36 meses
    ['amount' => 20160.00, 'term' => 48],   // >36 meses
    ['amount' => 21344.00, 'term' => 24],   // <36 meses
    ['amount' => 21168.00, 'term' => 48],   // >36 meses
];

echo "Creando contratos con datos reales...\n";
$realContracts = [];
foreach ($salesData as $index => $sale) {
    $realContracts[] = createTestContract(
        $advisor->employee_id, 
        $sale['amount'], 
        $sale['term'], 
        Carbon::now()
    );
    echo "Contrato " . ($index + 1) . ": $" . number_format($sale['amount'], 2) . " - {$sale['term']} meses\n";
}

echo "\nProcesando comisiones...\n";
$realCommissions = $commissionService->processCommissionsForPeriod($currentMonth, $currentYear);
showCommissionResults($realCommissions, 'COMISIONES CON DATOS REALES (20 VENTAS)');

// Mostrar cálculos detallados
echo "\n=== ANÁLISIS DETALLADO ===\n";
$totalShortTerm = 0; // <36 meses
$totalLongTerm = 0;  // >36 meses
$shortTermCount = 0;
$longTermCount = 0;

foreach ($salesData as $sale) {
    if ($sale['term'] < 36) {
        $totalShortTerm += $sale['amount'];
        $shortTermCount++;
    } else {
        $totalLongTerm += $sale['amount'];
        $longTermCount++;
    }
}

echo "Ventas <36 meses: {$shortTermCount} contratos, Total: $" . number_format($totalShortTerm, 2) . "\n";
echo "Ventas >36 meses: {$longTermCount} contratos, Total: $" . number_format($totalLongTerm, 2) . "\n";
echo "Total general: " . count($salesData) . " contratos, $" . number_format($totalShortTerm + $totalLongTerm, 2) . "\n";

// Cálculo manual para verificación
$commissionShortTerm = $totalShortTerm * 0.042; // 4.2% para <36 meses
$commissionLongTerm = $totalLongTerm * 0.03;    // 3.0% para >36 meses
$totalCommission = $commissionShortTerm + $commissionLongTerm;

echo "\nCálculo manual de comisiones:\n";
echo "Comisión <36 meses (4.2%): $" . number_format($commissionShortTerm, 2) . "\n";
echo "Comisión >36 meses (3.0%): $" . number_format($commissionLongTerm, 2) . "\n";
echo "Comisión total: $" . number_format($totalCommission, 2) . "\n";
echo "Primer pago (70%): $" . number_format($totalCommission * 0.7, 2) . "\n";
echo "Segundo pago (30%): $" . number_format($totalCommission * 0.3, 2) . "\n";

echo "\n=== RESUMEN DE CÁLCULOS ===\n";
echo "Escenario con datos reales (20 ventas): " . count($realCommissions) . " comisiones generadas\n";
echo "Se esperan 40 comisiones totales (20 para este mes al 70%, 20 para el siguiente mes al 30%)\n";

echo "\n=== PRUEBA COMPLETADA ===\n";