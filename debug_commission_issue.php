<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Models\Employee;

echo "=== DEBUG COMMISSION PROCESSING ===\n\n";

// 1. Verificar contratos con advisor_id y financing_amount
echo "1. VERIFICANDO CONTRATOS...\n";
$totalContracts = Contract::count();
echo "Total contratos: {$totalContracts}\n";

$contractsWithAdvisor = Contract::whereNotNull('advisor_id')->count();
echo "Contratos con advisor_id: {$contractsWithAdvisor}\n";

$contractsWithFinancing = Contract::where('financing_amount', '>', 0)->count();
echo "Contratos con financing_amount > 0: {$contractsWithFinancing}\n";

$contractsEligible = Contract::whereNotNull('advisor_id')
    ->where('financing_amount', '>', 0)
    ->where('status', 'vigente')
    ->count();
echo "Contratos elegibles para comisión: {$contractsEligible}\n\n";

// 2. Mostrar algunos contratos de ejemplo
echo "2. CONTRATOS DE EJEMPLO (últimos 5)...\n";
$sampleContracts = Contract::with('advisor')
    ->orderBy('contract_id', 'desc')
    ->limit(5)
    ->get();

foreach ($sampleContracts as $contract) {
    echo "Contract ID: {$contract->contract_id}\n";
    echo "  - Advisor ID: " . ($contract->advisor_id ?? 'NULL') . "\n";
    echo "  - Financing Amount: {$contract->financing_amount}\n";
    echo "  - Status: {$contract->status}\n";
    echo "  - Sign Date: {$contract->sign_date}\n";
    echo "  - Term Months: {$contract->term_months}\n";
    echo "  - Advisor Name: " . ($contract->advisor ? $contract->advisor->full_name : 'NO ADVISOR') . "\n";
    echo "  ---\n";
}

// 3. Verificar comisiones existentes
echo "\n3. VERIFICANDO COMISIONES EXISTENTES...\n";
$totalCommissions = Commission::count();
echo "Total comisiones: {$totalCommissions}\n";

$currentMonth = date('n');
$currentYear = date('Y');
echo "Período actual: {$currentMonth}/{$currentYear}\n";

$commissionsThisMonth = Commission::where('period_month', $currentMonth)
    ->where('period_year', $currentYear)
    ->count();
echo "Comisiones este mes: {$commissionsThisMonth}\n\n";

// 4. Verificar empleados/asesores
echo "4. VERIFICANDO EMPLEADOS/ASESORES...\n";
$totalEmployees = Employee::count();
echo "Total empleados: {$totalEmployees}\n";

$advisors = Employee::whereHas('contracts')->count();
echo "Empleados con contratos: {$advisors}\n\n";

// 5. Intentar procesar comisiones para el mes actual
echo "5. PROCESANDO COMISIONES PARA EL MES ACTUAL...\n";
try {
    $commissionService = app(CommissionService::class);
    
    // Primero eliminar comisiones existentes del período para reprocessar
    $deleted = Commission::where('period_month', $currentMonth)
        ->where('period_year', $currentYear)
        ->delete();
    echo "Comisiones eliminadas del período actual: {$deleted}\n";
    
    $result = $commissionService->processCommissionsForPeriod($currentMonth, $currentYear);
    echo "Comisiones procesadas: " . count($result) . "\n";
    
    if (count($result) > 0) {
        echo "\nDETALLE DE COMISIONES CREADAS:\n";
        foreach ($result as $commission) {
            echo "- Commission ID: {$commission->commission_id}\n";
            echo "  Employee ID: {$commission->employee_id}\n";
            echo "  Contract ID: {$commission->contract_id}\n";
            echo "  Amount: {$commission->commission_amount}\n";
            echo "  Type: {$commission->commission_type}\n";
            echo "  Status: {$commission->status}\n";
            echo "  Payment Status: {$commission->payment_status}\n";
            echo "  ---\n";
        }
    } else {
        echo "\n❌ NO SE CREARON COMISIONES\n";
        echo "\nPOSIBLES CAUSAS:\n";
        echo "1. No hay contratos con advisor_id asignado\n";
        echo "2. No hay contratos con financing_amount > 0\n";
        echo "3. Los contratos no tienen status 'vigente'\n";
        echo "4. Ya existen comisiones para estos contratos\n";
        
        // Verificar contratos específicos del mes actual
        echo "\nCONTRATOS DEL MES ACTUAL:\n";
        $currentMonthContracts = Contract::with('advisor')
            ->whereMonth('sign_date', $currentMonth)
            ->whereYear('sign_date', $currentYear)
            ->get();
            
        echo "Contratos firmados este mes: " . $currentMonthContracts->count() . "\n";
        
        foreach ($currentMonthContracts as $contract) {
            echo "Contract {$contract->contract_id}:\n";
            echo "  - Advisor: " . ($contract->advisor_id ? 'SÍ (' . $contract->advisor_id . ')' : 'NO') . "\n";
            echo "  - Financing: {$contract->financing_amount}\n";
            echo "  - Status: {$contract->status}\n";
            echo "  - Sign Date: {$contract->sign_date}\n";
            
            // Verificar si ya existe comisión
            $existingCommission = Commission::where('contract_id', $contract->contract_id)
                ->where('period_month', $currentMonth)
                ->where('period_year', $currentYear)
                ->first();
            echo "  - Comisión existente: " . ($existingCommission ? 'SÍ' : 'NO') . "\n";
            echo "  ---\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEBUG ===\n";