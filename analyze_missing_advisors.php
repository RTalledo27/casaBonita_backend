<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 ANALIZANDO CONTRATOS SIN ASESOR\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Obtener contratos sin asesor con sus datos de Logicware
$contracts = DB::table('contracts')
    ->whereNull('advisor_id')
    ->limit(10)
    ->get();

$total = DB::table('contracts')->count();
$withAdvisor = DB::table('contracts')->whereNotNull('advisor_id')->count();
$withoutAdvisor = DB::table('contracts')->whereNull('advisor_id')->count();

echo "📊 ESTADÍSTICAS:\n";
echo "   Total contratos: {$total}\n";
echo "   ✅ Con asesor: {$withAdvisor} (" . round($withAdvisor/$total*100, 1) . "%)\n";
echo "   ❌ Sin asesor: {$withoutAdvisor} (" . round($withoutAdvisor/$total*100, 1) . "%)\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🔍 PRIMEROS 10 CONTRATOS SIN ASESOR:\n\n";

foreach ($contracts as $contract) {
    echo "📄 Contrato: {$contract->contract_number}\n";
    
    if ($contract->logicware_data) {
        $data = json_decode($contract->logicware_data, true);
        
        // Buscar el seller en diferentes ubicaciones del JSON
        $seller = null;
        
        if (isset($data['seller'])) {
            $seller = $data['seller'];
        } elseif (isset($data['document']['seller'])) {
            $seller = $data['document']['seller'];
        } elseif (isset($data['documents'][0]['seller'])) {
            $seller = $data['documents'][0]['seller'];
        }
        
        if ($seller) {
            echo "   Vendedor en Logicware: '{$seller}'\n";
        } else {
            echo "   ❌ NO SE ENCONTRÓ CAMPO 'seller' en logicware_data\n";
            echo "   Keys disponibles: " . implode(', ', array_keys($data)) . "\n";
            
            // Mostrar estructura completa para debuggear
            if (isset($data['documents'][0])) {
                echo "   Keys en documents[0]: " . implode(', ', array_keys($data['documents'][0])) . "\n";
            }
        }
    } else {
        echo "   ❌ NO tiene logicware_data\n";
    }
    
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
