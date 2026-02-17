<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;

$defaultBono = LotFinancialTemplate::BONO_TECHO_PROPIO_DEFAULT;

// Set bono for all templates with precio_venta > 0
$count = LotFinancialTemplate::where('precio_venta', '>', 0)
    ->where(function($q) {
        $q->whereNull('bono_techo_propio')
          ->orWhere('bono_techo_propio', 0);
    })
    ->update(['bono_techo_propio' => $defaultBono]);

echo "Updated bono_techo_propio for {$count} templates (S/ {$defaultBono})\n";

// Calculate precio_total_real for all
$templates = LotFinancialTemplate::where('precio_venta', '>', 0)->get();
$updated = 0;
foreach ($templates as $t) {
    $totalReal = round((float) $t->precio_venta + (float) $t->bono_techo_propio, 2);
    $t->update(['precio_total_real' => $totalReal]);
    $updated++;
}

echo "Calculated precio_total_real for {$updated} templates\n";

// Show sample
$sample = LotFinancialTemplate::where('precio_venta', '>', 0)->first();
if ($sample) {
    echo "\nSample (lot_id: {$sample->lot_id}):\n";
    echo "  precio_venta: S/ {$sample->precio_venta}\n";
    echo "  bono_techo_propio: S/ {$sample->bono_techo_propio}\n";
    echo "  precio_total_real: S/ {$sample->precio_total_real}\n";
    echo "  5% del total real: S/ " . round((float) $sample->precio_total_real * 0.05, 2) . "\n";
}
