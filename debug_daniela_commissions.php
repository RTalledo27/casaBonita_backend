<?php

require_once __DIR__ . '/bootstrap/app.php';

use Modules\Sales\app\Models\Contract;
use Modules\HumanResources\app\Models\Commission;
use Modules\HumanResources\Services\CommissionService;
use Modules\Lots\app\Models\LotFinancialTemplate;

// Buscar empleado DANIELA AIRAM MERINO VALIENTE
$employee = \Modules\HumanResources\app\Models\Employee::where('first_name', 'LIKE', '%DANIELA%')
    ->where('last_name', 'LIKE', '%AIRAM%')
    ->first();

if (!$employee) {
    echo "No se encontró empleado DANIELA AIRAM\n";
    exit;
}

echo "=== EMPLEADO ENCONTRADO ===\n";
echo "ID: {$employee->employee_id}\n";
echo "Nombre: {$employee->first_name} {$employee->last_name}\n";
echo "\n";

// Buscar contratos de DANIELA en junio 2025
$contracts = Contract::where('advisor_id', $employee->employee_id)
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2025)
    ->get();

echo "=== CONTRATOS JUNIO 2025 ===\n";
echo "Total contratos: " . $contracts->count() . "\n";

foreach ($contracts as $contract) {
    echo "\n--- Contrato {$contract->contract_id} ---\n";
    echo "Monto financiamiento: S/ " . number_format($contract->financing_amount, 2) . "\n";
    echo "Plazo: {$contract->term_months} meses\n";
    echo "Fecha firma: {$contract->sign_date}\n";
    
    // Verificar si tiene template financiero
    $lot = $contract->getLot();
    if ($lot && $lot->lotFinancialTemplate) {
        $template = $lot->lotFinancialTemplate;
        echo "Template ID: {$template->id}\n";
        echo "Template monto: S/ " . number_format($template->financing_amount, 2) . "\n";
        echo "Template plazo: {$template->term_months} meses\n";
    } else {
        echo "SIN TEMPLATE FINANCIERO\n";
    }
}

// Buscar comisiones existentes
echo "\n=== COMISIONES EXISTENTES ===\n";
$commissions = Commission::where('employee_id', $employee->employee_id)
    ->where('period_month', 6)
    ->where('period_year', 2025)
    ->orderBy('contract_id')
    ->orderBy('parent_commission_id')
    ->get();

echo "Total comisiones: " . $commissions->count() . "\n";

$totalPayable = 0;
foreach ($commissions as $commission) {
    $type = $commission->parent_commission_id ? 'HIJA' : 'PADRE';
    $payable = $commission->is_payable ? 'PAGABLE' : 'NO PAGABLE';
    
    echo "\n--- Comisión {$commission->commission_id} ({$type}) ---\n";
    echo "Contrato: {$commission->contract_id}\n";
    echo "Porcentaje BD: {$commission->commission_percentage}%\n";
    echo "Monto: S/ " . number_format($commission->commission_amount, 2) . "\n";
    echo "Estado: {$payable}\n";
    echo "Ventas count: {$commission->sales_count}\n";
    echo "Payment part: {$commission->payment_part}\n";
    echo "Payment percentage: {$commission->payment_percentage}%\n";
    
    if ($commission->is_payable) {
        $totalPayable += $commission->commission_amount;
    }
}

echo "\n=== RESUMEN ===\n";
echo "Total comisiones PAGABLES: S/ " . number_format($totalPayable, 2) . "\n";
echo "Excel esperado: S/ 3,971.26\n";
echo "Diferencia: S/ " . number_format($totalPayable - 3971.26, 2) . "\n";

// Simular cálculo manual para verificar lógica
echo "\n=== SIMULACIÓN CÁLCULO MANUAL ===\n";

$commissionService = app(CommissionService::class);

foreach ($contracts as $contract) {
    echo "\n--- Simulando contrato {$contract->contract_id} ---\n";
    
    // Contar ventas del asesor hasta la fecha del contrato
    $salesCount = $commissionService->getAdvisorFinancedSalesCount($contract->advisor_id, $contract->sign_date);
    echo "Ventas count: {$salesCount}\n";
    
    // Verificar si tiene template
    $lot = $contract->getLot();
    if ($lot && $lot->lotFinancialTemplate) {
        echo "USANDO TEMPLATE:\n";
        $template = $lot->lotFinancialTemplate;
        $financingAmount = $template->financing_amount;
        $termMonths = $template->term_months;
        
        // Usar getCommissionRate (retorna porcentajes)
        $ratePercent = $commissionService->getCommissionRate($salesCount, $termMonths);
        $rateDecimal = $ratePercent / 100;
        $commissionAmount = $financingAmount * $rateDecimal;
        
        echo "Monto base: S/ " . number_format($financingAmount, 2) . "\n";
        echo "Tasa porcentaje: {$ratePercent}%\n";
        echo "Tasa decimal: {$rateDecimal}\n";
        echo "Comisión calculada: S/ " . number_format($commissionAmount, 2) . "\n";
    } else {
        echo "SIN TEMPLATE - USANDO FALLBACK:\n";
        $financingAmount = $contract->financing_amount;
        $termMonths = $contract->term_months;
        
        // Usar calculateCommissionRate (retorna decimales)
        $rateDecimal = $commissionService->calculateCommissionRate($financingAmount, $termMonths);
        $ratePercent = $rateDecimal * 100;
        $commissionAmount = $financingAmount * $rateDecimal;
        
        echo "Monto base: S/ " . number_format($financingAmount, 2) . "\n";
        echo "Tasa decimal: {$rateDecimal}\n";
        echo "Tasa porcentaje: {$ratePercent}%\n";
        echo "Comisión calculada: S/ " . number_format($commissionAmount, 2) . "\n";
    }
    
    // Simular división 50/50 para < 10 ventas
    if ($salesCount < 10) {
        $firstPayment = $commissionAmount * 0.5;
        $secondPayment = $commissionAmount * 0.5;
        echo "División 50/50:\n";
        echo "  Primer pago: S/ " . number_format($firstPayment, 2) . "\n";
        echo "  Segundo pago: S/ " . number_format($secondPayment, 2) . "\n";
    }
}

echo "\n=== FIN ANÁLISIS ===\n";