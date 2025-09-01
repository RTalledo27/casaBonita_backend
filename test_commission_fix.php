<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\app\Models\Commission;
use Modules\Contracts\app\Models\Contract;
use Modules\HumanResources\app\Services\CommissionService;
use Modules\HumanResources\app\Repositories\CommissionRepository;
use Modules\HumanResources\app\Repositories\EmployeeRepository;

echo "=== PRUEBA DE CORRECCIÓN DE CÁLCULO DE COMISIONES ===\n\n";

// Instanciar el servicio
$commissionRepo = new CommissionRepository();
$employeeRepo = new EmployeeRepository();
$commissionService = new CommissionService($commissionRepo, $employeeRepo);

// Buscar contratos de DANIELA AIRAM (ID: 7) en junio 2025
$contracts = Contract::with(['advisor', 'lot.lotFinancialTemplate'])
    ->where('advisor_id', 7)
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2025)
    ->where('status', 'vigente')
    ->get();

echo "Contratos encontrados para DANIELA AIRAM en Junio 2025: " . $contracts->count() . "\n\n";

foreach ($contracts as $contract) {
    echo "--- CONTRATO ID: {$contract->contract_id} ---\n";
    echo "Cliente: {$contract->client_name}\n";
    echo "Fecha firma: {$contract->sign_date}\n";
    echo "Monto contrato: S/ " . number_format($contract->financing_amount, 2) . "\n";
    echo "Plazo: {$contract->term_months} meses\n";
    
    // Verificar si tiene template
    if ($contract->lot && $contract->lot->lotFinancialTemplate) {
        $template = $contract->lot->lotFinancialTemplate;
        echo "Template ID: {$template->id}\n";
        echo "Monto template: S/ " . number_format($template->financing_amount, 2) . "\n";
        echo "Plazo template: {$template->term_months} meses\n";
        
        // Simular cálculo con el método corregido
        try {
            // Usar reflexión para acceder al método privado calculateCommissionFromTemplate
            $reflection = new ReflectionClass($commissionService);
            $method = $reflection->getMethod('calculateCommissionFromTemplate');
            $method->setAccessible(true);
            
            $commissionData = $method->invoke($commissionService, $contract);
            
            echo "\n--- CÁLCULO CORREGIDO ---\n";
            echo "Ventas del asesor: {$commissionData['sales_count']}\n";
            echo "Tasa de comisión: " . ($commissionData['commission_rate'] * 100) . "%\n";
            echo "Comisión calculada: S/ " . number_format($commissionData['commission_amount'], 2) . "\n";
            echo "Fuente: {$commissionData['financial_source']}\n";
            
        } catch (Exception $e) {
            echo "Error en cálculo: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Sin template financiero\n";
    }
    
    // Buscar comisiones existentes
    $existingCommissions = Commission::where('contract_id', $contract->contract_id)
        ->where('employee_id', 7)
        ->where('period_month', 6)
        ->where('period_year', 2025)
        ->get();
    
    echo "\n--- COMISIONES EXISTENTES ---\n";
    $totalPayable = 0;
    foreach ($existingCommissions as $commission) {
        $type = $commission->parent_commission_id ? 'HIJA' : 'PADRE';
        $payable = $commission->is_payable ? 'PAGABLE' : 'NO PAGABLE';
        echo "ID: {$commission->commission_id} | Tipo: {$type} | {$payable} | S/ " . number_format($commission->commission_amount, 2) . "\n";
        
        if ($commission->is_payable) {
            $totalPayable += $commission->commission_amount;
        }
    }
    
    echo "Total PAGABLE para este contrato: S/ " . number_format($totalPayable, 2) . "\n";
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

// Calcular total general de comisiones pagables de DANIELA
$totalDanielaPayable = Commission::where('employee_id', 7)
    ->where('period_month', 6)
    ->where('period_year', 2025)
    ->where('is_payable', true)
    ->sum('commission_amount');

echo "=== RESUMEN FINAL ===\n";
echo "Total comisiones PAGABLES de DANIELA AIRAM: S/ " . number_format($totalDanielaPayable, 2) . "\n";
echo "Esperado según Excel del usuario: S/ 3,971.26\n";
echo "Diferencia: S/ " . number_format(abs($totalDanielaPayable - 3971.26), 2) . "\n";

if (abs($totalDanielaPayable - 3971.26) < 0.01) {
    echo "✅ CÁLCULO CORRECTO - Coincide con Excel del usuario\n";
} else {
    echo "❌ DISCREPANCIA - Revisar cálculos\n";
}