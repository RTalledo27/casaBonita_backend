<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\Contract;
use Illuminate\Support\Facades\DB;

echo "=== Verificación de datos de comisiones ===\n";
echo "Fecha: " . now()->format('Y-m-d H:i:s') . "\n\n";

try {
    // Verificar total de comisiones
    $totalCommissions = Commission::count();
    echo "Total de comisiones en la base de datos: {$totalCommissions}\n\n";
    
    if ($totalCommissions === 0) {
        echo "No hay comisiones en la base de datos.\n";
        exit(0);
    }
    
    // Verificar comisiones que no requieren verificación
    $commissionsNoVerification = Commission::where('requires_client_payment_verification', false)->count();
    echo "Comisiones que NO requieren verificación: {$commissionsNoVerification}\n";
    
    // Verificar comisiones que sí requieren verificación
    $commissionsWithVerification = Commission::where('requires_client_payment_verification', true)->count();
    echo "Comisiones que SÍ requieren verificación: {$commissionsWithVerification}\n\n";
    
    // Mostrar algunos ejemplos de comisiones
    echo "=== Ejemplos de comisiones (primeras 5) ===\n";
    $sampleCommissions = Commission::with('contract')->take(5)->get();
    
    foreach ($sampleCommissions as $commission) {
        echo "Comisión ID: {$commission->id}\n";
        echo "  Contract ID: {$commission->contract_id}\n";
        
        if ($commission->contract) {
            echo "  Contract Number: {$commission->contract->contract_number}\n";
        } else {
            echo "  Contract Number: No encontrado\n";
        }
        
        echo "  Payment Part: " . ($commission->payment_part ?? 'N/A') . "\n";
        echo "  Requiere verificación: " . ($commission->requires_client_payment_verification ? 'Sí' : 'No') . "\n";
        echo "  Estado: {$commission->payment_verification_status}\n";
        echo "  Elegible: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
        echo "\n";
    }
    
    // Buscar contratos que tengan comisiones
    echo "=== Contratos con comisiones ===\n";
    $contractsWithCommissions = Commission::with('contract')
        ->select('contract_id', DB::raw('COUNT(*) as commission_count'))
        ->groupBy('contract_id')
        ->orderBy('commission_count', 'desc')
        ->take(10)
        ->get();
    
    foreach ($contractsWithCommissions as $item) {
        $contractNumber = $item->contract ? $item->contract->contract_number : 'N/A';
        echo "Contrato: {$contractNumber} (ID: {$item->contract_id}) - {$item->commission_count} comisiones\n";
    }
    
    echo "\n=== Verificar contrato CON20257868 específicamente ===\n";
    $contract = Contract::where('contract_number', 'CON20257868')->first();
    
    if ($contract) {
        echo "Contrato encontrado - ID: {$contract->contract_id}\n";
        
        $commissions = Commission::where('contract_id', $contract->contract_id)->get();
        echo "Comisiones para este contrato: " . $commissions->count() . "\n";
        
        if ($commissions->count() > 0) {
            foreach ($commissions as $commission) {
                echo "  - Comisión ID: {$commission->id}, Payment Part: " . ($commission->payment_part ?? 'N/A') . "\n";
            }
        }
    } else {
        echo "Contrato CON20257868 no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\nVerificación completada.\n";