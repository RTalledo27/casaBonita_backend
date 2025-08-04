<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\app\Services\CommissionService;
use Illuminate\Support\Facades\DB;

echo "Debug detallado del procesamiento de comisiones...\n\n";

$month = 7;
$year = 2025;

echo "1. Verificando contratos para julio 2025...\n";
$contracts = Contract::with('advisor')
                   ->whereMonth('sign_date', $month)
                   ->whereYear('sign_date', $year)
                   ->where('status', 'vigente')
                   ->whereNotNull('advisor_id')
                   ->get();

echo "Contratos encontrados: " . $contracts->count() . "\n";

foreach ($contracts as $contract) {
    echo "\n--- Procesando contrato {$contract->contract_id} ---\n";
    echo "Advisor ID: {$contract->advisor_id}\n";
    echo "Financing Amount: {$contract->financing_amount}\n";
    echo "Term Months: {$contract->term_months}\n";
    echo "Sign Date: {$contract->sign_date}\n";
    
    // Verificar si ya existe comisión
    $existingCommission = Commission::where('contract_id', $contract->contract_id)
                                   ->where('period_month', $month)
                                   ->where('period_year', $year)
                                   ->first();
    
    echo "Comisión existente: " . ($existingCommission ? 'SÍ (ID: ' . $existingCommission->commission_id . ')' : 'NO') . "\n";
    
    if (!$existingCommission) {
        echo "Advisor existe: " . ($contract->advisor ? 'SÍ' : 'NO') . "\n";
        echo "Financing amount > 0: " . ($contract->financing_amount > 0 ? 'SÍ' : 'NO') . "\n";
        
        if ($contract->advisor && $contract->financing_amount > 0) {
            // Simular cálculo de comisión
            $commissionService = new CommissionService();
            $totalCommissionAmount = $commissionService->calculateCommission($contract);
            echo "Total commission amount: {$totalCommissionAmount}\n";
            
            if ($totalCommissionAmount > 0) {
                echo "✓ Este contrato DEBERÍA generar comisión\n";
                
                // Intentar procesar solo este contrato
                try {
                    echo "Intentando procesar comisión...\n";
                    $result = $commissionService->processCommissionsForPeriod($month, $year);
                    echo "Resultado del procesamiento: " . count($result) . " comisiones creadas\n";
                    
                    if (count($result) > 0) {
                        foreach ($result as $commission) {
                            echo "- Comisión creada: ID {$commission->commission_id}, Monto: {$commission->commission_amount}\n";
                        }
                    }
                } catch (Exception $e) {
                    echo "ERROR al procesar: " . $e->getMessage() . "\n";
                    echo "Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "\n";
                }
                
                break; // Solo procesar el primer contrato válido para debug
            } else {
                echo "✗ Total commission amount es 0\n";
            }
        } else {
            echo "✗ No cumple condiciones (advisor o financing_amount)\n";
        }
    } else {
        echo "✗ Ya existe comisión para este contrato\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Verificando comisiones existentes después del procesamiento...\n";
$existingCommissions = Commission::where('period_month', $month)
                                ->where('period_year', $year)
                                ->get();
echo "Comisiones encontradas: " . $existingCommissions->count() . "\n";

foreach ($existingCommissions as $commission) {
    echo "- ID: {$commission->commission_id}, Contract: {$commission->contract_id}, Amount: {$commission->commission_amount}\n";
}