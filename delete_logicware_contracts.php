<?php

/**
 * Eliminar todos los contratos importados desde Logicware
 * para realizar una importación limpia
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== LIMPIEZA DE CONTRATOS LOGICWARE ===\n\n";

try {
    // Buscar contratos con números de Logicware (202511-)
    $contracts = DB::table('contracts')
        ->where('contract_number', 'LIKE', '202511-%')
        ->get();
    
    echo "📋 Contratos a eliminar: " . $contracts->count() . "\n\n";
    
    if ($contracts->isEmpty()) {
        echo "ℹ️  No hay contratos de Logicware para eliminar.\n";
        exit(0);
    }
    
    foreach ($contracts as $contract) {
        echo "  • ID: {$contract->contract_id}\n";
        echo "    Número: {$contract->contract_number}\n";
        echo "    Cliente ID: {$contract->client_id}\n";
        echo "    Lote ID: {$contract->lot_id}\n";
        
        // Eliminar cronogramas asociados
        $schedulesDeleted = DB::table('payment_schedules')
            ->where('contract_id', $contract->contract_id)
            ->delete();
        
        echo "    Cronogramas eliminados: {$schedulesDeleted}\n\n";
    }
    
    // Eliminar contratos
    $contractsDeleted = DB::table('contracts')
        ->where('contract_number', 'LIKE', '202511-%')
        ->delete();
    
    echo "✅ COMPLETADO\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Contratos eliminados: {$contractsDeleted}\n";
    echo "\n✨ Base de datos limpia para nueva importación\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    exit(1);
}

echo "\n=== FIN ===\n";
