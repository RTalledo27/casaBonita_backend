<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ANÁLISIS COMPLETO DE DATOS LOGICWARE ===\n\n";

$contract = DB::table('contracts')->where('source', 'logicware')->first();

if (!$contract || !$contract->logicware_data) {
    echo "❌ No hay datos de Logicware\n";
    exit;
}

$data = json_decode($contract->logicware_data, true);

echo "📦 UNIT DATA (units[0]):\n";
if (isset($data['units'][0])) {
    echo json_encode($data['units'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

echo "\n\n💰 FINANCING DATA:\n";
if (isset($data['financing'])) {
    echo json_encode($data['financing'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

echo "\n\n✅ Análisis completado\n";
