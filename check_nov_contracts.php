<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "🔍 ANÁLISIS DE CONTRATOS IMPORTADOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Contratos de noviembre 2025
$novContracts = DB::table('contracts')
    ->where('source', 'logicware')
    ->where('contract_number', 'like', '202511-%')
    ->get();

echo "📊 Contratos de noviembre 2025: " . $novContracts->count() . "\n\n";

if ($novContracts->count() > 0) {
    echo "Detalle:\n";
    foreach ($novContracts->take(5) as $contract) {
        echo "  - {$contract->contract_number} (ID: {$contract->contract_id})\n";
        
        $schedules = DB::table('payment_schedules')
            ->where('contract_id', $contract->contract_id)
            ->get();
        
        $byType = $schedules->groupBy('type');
        
        echo "    Cuotas: {$schedules->count()} | ";
        echo "Tipos: ";
        foreach ($byType as $type => $items) {
            echo "{$type}({$items->count()}) ";
        }
        echo "\n";
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 TODOS LOS CONTRATOS LOGICWARE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$allContracts = DB::table('contracts')
    ->where('source', 'logicware')
    ->select('contract_number', 'created_at')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

echo "Últimos 10 contratos:\n";
foreach ($allContracts as $contract) {
    echo "  - {$contract->contract_number} (Creado: {$contract->created_at})\n";
}
