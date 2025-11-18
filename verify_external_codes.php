<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$lot = \Illuminate\Support\Facades\DB::table('lots')->where('lot_id', 454)->first();

echo "🔍 Lote 454:\n";
echo "   num_lot: " . ($lot->num_lot ?? 'NULL') . "\n";
echo "   external_code: " . ($lot->external_code ?? 'NULL') . "\n";
echo "   manzana_id: " . ($lot->manzana_id ?? 'NULL') . "\n";

// Ver external_code de varios lotes
echo "\n📋 External codes de varios lotes:\n";
$lots = \Illuminate\Support\Facades\DB::table('lots')
    ->whereIn('lot_id', [454, 572, 328, 660, 626])
    ->get(['lot_id', 'num_lot', 'external_code', 'manzana_id']);

foreach ($lots as $l) {
    echo "   • Lote {$l->lot_id}: num_lot={$l->num_lot}, external_code=" . ($l->external_code ?: 'EMPTY/NULL') . "\n";
}
