<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ANÁLISIS DE ASESORES EN CONTRATOS ===\n\n";

$withAdvisor = DB::table('contracts')->whereNotNull('advisor_id')->count();
$withoutAdvisor = DB::table('contracts')->whereNull('advisor_id')->count();
$total = $withAdvisor + $withoutAdvisor;

echo "Total contratos: {$total}\n";
echo "✅ Con asesor: {$withAdvisor} (" . round(($withAdvisor/$total)*100, 1) . "%)\n";
echo "❌ Sin asesor: {$withoutAdvisor} (" . round(($withoutAdvisor/$total)*100, 1) . "%)\n\n";

// Mostrar algunos contratos sin asesor para analizar
echo "📋 Contratos sin asesor (primeros 10):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$contracts = DB::table('contracts')
    ->whereNull('advisor_id')
    ->limit(10)
    ->get();

foreach ($contracts as $contract) {
    echo "• ID: {$contract->contract_id} - Número: {$contract->contract_number}\n";
}

echo "\n=== FIN ===\n";
