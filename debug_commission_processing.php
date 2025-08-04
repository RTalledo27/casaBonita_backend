<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Commission;
use Illuminate\Support\Facades\DB;

echo "Debug del procesamiento de comisiones...\n\n";

try {
    $month = 1;
    $year = 2025;
    
    echo "1. Verificando contratos para enero 2025...\n";
    $contracts = Contract::with('advisor')
                       ->whereMonth('sign_date', $month)
                       ->whereYear('sign_date', $year)
                       ->where('status', 'vigente')
                       ->whereNotNull('advisor_id')
                       ->get();
    
    echo "Contratos encontrados: " . $contracts->count() . "\n";
    
    foreach($contracts as $contract) {
        echo "- Contract ID: {$contract->contract_id}, Advisor: {$contract->advisor_id}, Amount: {$contract->financing_amount}\n";
    }
    
    echo "\n2. Verificando comisiones existentes para enero 2025...\n";
    $existingCommissions = Commission::whereMonth('period_month', $month)
                                   ->where('period_year', $year)
                                   ->get();
    
    echo "Comisiones existentes: " . $existingCommissions->count() . "\n";
    
    if ($existingCommissions->count() > 0) {
        echo "Comisiones ya existen para este período. Eliminando para reprocessar...\n";
        Commission::whereMonth('period_month', $month)
                 ->where('period_year', $year)
                 ->delete();
        echo "Comisiones eliminadas.\n";
    }
    
    echo "\n3. Procesando comisiones...\n";
    $service = app(CommissionService::class);
    $result = $service->processCommissionsForPeriod($month, $year);
    
    echo "Comisiones procesadas: " . count($result) . "\n\n";
    
    if (count($result) > 0) {
        echo "4. Verificando comisiones creadas...\n";
        $newCommissions = Commission::whereMonth('period_month', $month)
                                  ->where('period_year', $year)
                                  ->get();
        
        echo "Nuevas comisiones creadas: " . $newCommissions->count() . "\n";
        
        foreach($newCommissions as $commission) {
            echo "- Commission ID: {$commission->commission_id}\n";
            echo "  Commission Period: " . ($commission->commission_period ?? 'NULL') . "\n";
            echo "  Payment Period: " . ($commission->payment_period ?? 'NULL') . "\n";
            echo "  Payment Percentage: {$commission->payment_percentage}%\n";
            echo "  Status: {$commission->status}\n";
            echo "  Parent ID: " . ($commission->parent_commission_id ?? 'NULL') . "\n";
            echo "  Payment Part: {$commission->payment_part}\n";
            echo "  Payment Type: {$commission->payment_type}\n";
            echo "  Amount: {$commission->commission_amount}\n";
            echo "---\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}