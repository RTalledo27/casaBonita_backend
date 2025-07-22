<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Carbon\Carbon;

echo "=== PRUEBA LIMPIA CON DATOS ESPECÍFICOS DEL USUARIO ===\n\n";

// Crear servicio de comisiones
$commissionService = app(CommissionService::class);

// Datos proporcionados por el usuario
$salesData = [
    ['amount' => 120000, 'term' => '>36'],
    ['amount' => 95000, 'term' => '<36'],
    ['amount' => 110000, 'term' => '>36'],
    ['amount' => 85000, 'term' => '<36'],
    ['amount' => 130000, 'term' => '>36'],
    ['amount' => 75000, 'term' => '<36'],
    ['amount' => 140000, 'term' => '>36'],
    ['amount' => 90000, 'term' => '<36'],
    ['amount' => 125000, 'term' => '>36'],
    ['amount' => 80000, 'term' => '<36'],
    ['amount' => 115000, 'term' => '>36'],
    ['amount' => 100000, 'term' => '<36'],
    ['amount' => 135000, 'term' => '>36'],
    ['amount' => 88000, 'term' => '<36'],
    ['amount' => 105000, 'term' => '>36'],
    ['amount' => 92000, 'term' => '<36'],
    ['amount' => 118000, 'term' => '>36'],
    ['amount' => 78000, 'term' => '<36'],
    ['amount' => 128000, 'term' => '>36'],
    ['amount' => 82000, 'term' => '<36']
];

// Función para crear contrato de prueba
function createTestContract($advisorId, $financingAmount, $termMonths, $signDate) {
    return Contract::create([
        'contract_number' => 'CLEAN-TEST-' . uniqid(),
        'reservation_id' => 1,
        'advisor_id' => $advisorId,
        'sign_date' => $signDate,
        'total_price' => $financingAmount + 10000,
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

// LIMPIAR COMPLETAMENTE TODOS LOS DATOS DEL ASESOR
$currentMonth = date('n');
$currentYear = date('Y');

echo "=== LIMPIANDO TODOS LOS DATOS DEL ASESOR ===\n";

// Eliminar todas las comisiones del asesor
$deletedCommissions = Commission::where('employee_id', $advisor->employee_id)->delete();
echo "Comisiones eliminadas: {$deletedCommissions}\n";

// Eliminar TODOS los contratos del asesor (no solo los de prueba)
$deletedContracts = Contract::where('advisor_id', $advisor->employee_id)->delete();
echo "Contratos eliminados: {$deletedContracts}\n";

echo "Limpieza completa realizada.\n\n";

// Verificar que no hay contratos
$remainingContracts = Contract::where('advisor_id', $advisor->employee_id)->count();
echo "Contratos restantes: {$remainingContracts}\n\n";

if ($remainingContracts > 0) {
    echo "ERROR: Aún hay contratos del asesor. Abortando prueba.\n";
    exit(1);
}

// Crear contratos con los datos del usuario
echo "=== CREANDO 20 CONTRATOS CON DATOS DEL USUARIO ===\n";
$contracts = [];
$totalAmount = 0;
$shortTermCount = 0;
$longTermCount = 0;

foreach ($salesData as $index => $sale) {
    $termMonths = ($sale['term'] === '>36') ? 48 : 24; // >36 = 48 meses, <36 = 24 meses
    $contract = createTestContract($advisor->employee_id, $sale['amount'], $termMonths, Carbon::now());
    $contracts[] = $contract;
    $totalAmount += $sale['amount'];
    
    if ($sale['term'] === '>36') {
        $longTermCount++;
    } else {
        $shortTermCount++;
    }
    
    echo "Contrato " . ($index + 1) . ": $" . number_format($sale['amount']) . " - {$termMonths} meses\n";
}

echo "\n=== RESUMEN DE VENTAS ===\n";
echo "Total de ventas: 20\n";
echo "Ventas >36 meses: {$longTermCount}\n";
echo "Ventas <36 meses: {$shortTermCount}\n";
echo "Monto total: $" . number_format($totalAmount) . "\n\n";

// Verificar conteo de contratos antes de procesar
$contractCount = Contract::where('advisor_id', $advisor->employee_id)
    ->whereMonth('sign_date', $currentMonth)
    ->whereYear('sign_date', $currentYear)
    ->where('status', 'vigente')
    ->count();

echo "Contratos verificados en BD: {$contractCount}\n\n";

// Procesar comisiones
echo "=== PROCESANDO COMISIONES ===\n";
$commissions = $commissionService->processCommissionsForPeriod($currentMonth, $currentYear);

if (empty($commissions)) {
    echo "No se generaron comisiones.\n";
} else {
    echo "Se generaron " . count($commissions) . " comisiones:\n\n";
    
    $totalCommissionAmount = 0;
    $firstPaymentTotal = 0;
    $secondPaymentTotal = 0;
    
    // Mostrar solo las primeras 5 comisiones para no saturar la salida
    echo "--- PRIMERAS 5 COMISIONES ---\n";
    for ($i = 0; $i < min(5, count($commissions)); $i++) {
        $commission = $commissions[$i];
        echo "Comisión ID: {$commission->commission_id}\n";
        echo "Contrato ID: {$commission->contract_id}\n";
        echo "Monto de venta: $" . number_format($commission->sale_amount) . "\n";
        echo "Plazo: {$commission->installment_plan} meses\n";
        echo "Porcentaje: {$commission->commission_percentage}%\n";
        echo "Monto comisión: $" . number_format($commission->commission_amount, 2) . "\n";
        echo "Tipo de pago: {$commission->payment_type}\n";
        echo "Total comisión: $" . number_format($commission->total_commission_amount, 2) . "\n";
        echo "Cantidad de ventas: {$commission->sales_count}\n";
        echo "\n";
    }
    
    // Calcular totales
    foreach ($commissions as $commission) {
        $totalCommissionAmount += $commission->commission_amount;
        
        if ($commission->payment_type === 'first_payment') {
            $firstPaymentTotal += $commission->commission_amount;
        } elseif ($commission->payment_type === 'second_payment') {
            $secondPaymentTotal += $commission->commission_amount;
        }
    }
    
    echo "=== RESUMEN DE COMISIONES ===\n";
    echo "Total de comisiones generadas: " . count($commissions) . "\n";
    echo "Monto total de comisiones: $" . number_format($totalCommissionAmount, 2) . "\n";
    echo "Primer pago (70%): $" . number_format($firstPaymentTotal, 2) . "\n";
    echo "Segundo pago (30%): $" . number_format($secondPaymentTotal, 2) . "\n";
    
    // Verificar cálculos esperados
    echo "\n=== VERIFICACIÓN DE CÁLCULOS ===\n";
    
    // Con 20 ventas, debería ser 3% para >36 meses y 4.2% para <36 meses
    // División 70/30
    $expectedLongTermCommission = 0;
    $expectedShortTermCommission = 0;
    
    foreach ($salesData as $sale) {
        if ($sale['term'] === '>36') {
            $expectedLongTermCommission += $sale['amount'] * 0.03; // 3%
        } else {
            $expectedShortTermCommission += $sale['amount'] * 0.042; // 4.2%
        }
    }
    
    $expectedTotal = $expectedLongTermCommission + $expectedShortTermCommission;
    $expectedFirstPayment = $expectedTotal * 0.70;
    $expectedSecondPayment = $expectedTotal * 0.30;
    
    echo "Comisión esperada >36 meses (3%): $" . number_format($expectedLongTermCommission, 2) . "\n";
    echo "Comisión esperada <36 meses (4.2%): $" . number_format($expectedShortTermCommission, 2) . "\n";
    echo "Total esperado: $" . number_format($expectedTotal, 2) . "\n";
    echo "Primer pago esperado (70%): $" . number_format($expectedFirstPayment, 2) . "\n";
    echo "Segundo pago esperado (30%): $" . number_format($expectedSecondPayment, 2) . "\n";
    
    echo "\n=== COMPARACIÓN ===\n";
    echo "Total calculado: $" . number_format($totalCommissionAmount, 2) . "\n";
    echo "Total esperado: $" . number_format($expectedTotal, 2) . "\n";
    echo "Diferencia: $" . number_format(abs($totalCommissionAmount - $expectedTotal), 2) . "\n";
    
    if (abs($totalCommissionAmount - $expectedTotal) < 1) {
        echo "✅ CÁLCULOS CORRECTOS\n";
    } else {
        echo "❌ DIFERENCIA EN CÁLCULOS\n";
        
        // Mostrar información de depuración
        if (!empty($commissions)) {
            $sampleCommission = $commissions[0];
            echo "\nDEPURACIÓN:\n";
            echo "Sales count detectado: {$sampleCommission->sales_count}\n";
            echo "Porcentaje aplicado: {$sampleCommission->commission_percentage}%\n";
        }
    }
}

echo "\n=== PRUEBA COMPLETADA ===\n";