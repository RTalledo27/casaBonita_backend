<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNÓSTICO customer_payments + precio_contado ===\n\n";

// customer_payments table structure
echo "--- Columnas de customer_payments ---\n";
try {
    $cols = DB::select("SHOW COLUMNS FROM customer_payments");
    foreach ($cols as $col) {
        echo "  {$col->Field} | {$col->Type} | {$col->Null} | {$col->Default}\n";
    }
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// customer_payments data
echo "\n--- customer_payments registros ---\n";
try {
    $cnt = DB::table('customer_payments')->count();
    echo "  Total: {$cnt}\n";
    
    if ($cnt > 0) {
        $byMethod = DB::select("SELECT payment_method, COUNT(*) as cnt, SUM(amount) as total FROM customer_payments GROUP BY payment_method");
        echo "  Por método:\n";
        foreach ($byMethod as $m) {
            echo "    {$m->payment_method}: cnt={$m->cnt}, total=S/{$m->total}\n";
        }

        // Check if linked to contracts
        $linked = DB::select("SELECT COUNT(*) as cnt FROM customer_payments WHERE contract_id IS NOT NULL");
        echo "  Con contract_id: {$linked[0]->cnt}\n";

        $sample = DB::select("SELECT * FROM customer_payments LIMIT 5");
        echo "  Muestra:\n";
        foreach ($sample as $s) {
            echo "    " . json_encode($s) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}

// lot_financial_templates.precio_contado
echo "\n--- lot_financial_templates.precio_contado ---\n";
$pc = DB::select("
    SELECT COUNT(*) as total,
        SUM(CASE WHEN precio_contado IS NOT NULL AND precio_contado > 0 THEN 1 ELSE 0 END) as with_value,
        AVG(NULLIF(precio_contado, 0)) as avg_val,
        MIN(NULLIF(precio_contado, 0)) as min_val,
        MAX(precio_contado) as max_val
    FROM lot_financial_templates
");
echo "  Total templates: {$pc[0]->total}\n";
echo "  Con precio_contado > 0: {$pc[0]->with_value}\n";
echo "  Promedio: S/{$pc[0]->avg_val}\n";
echo "  Min: S/{$pc[0]->min_val}\n";
echo "  Max: S/{$pc[0]->max_val}\n";

// Sample precio_contado vs precio_venta vs precio_total_real
echo "\n--- Muestra: precio_contado vs otros precios ---\n";
$sample = DB::select("
    SELECT lot_id, precio_venta, precio_contado, bono_techo_propio, precio_total_real
    FROM lot_financial_templates
    WHERE precio_contado > 0
    LIMIT 10
");
foreach ($sample as $s) {
    echo "  lot_id={$s->lot_id}: venta=S/{$s->precio_venta}, contado=S/{$s->precio_contado}, bono=S/{$s->bono_techo_propio}, ptr=S/{$s->precio_total_real}\n";
}

// KEY: summary of the issue
echo "\n=== RESUMEN DEL PROBLEMA ===\n";
echo "Con 5% sobre total_price (viejo): 328 contratos califican\n";
echo "Con 5% sobre precio_total_real (nuevo, con bono): 97 contratos califican\n";
echo "DIFERENCIA: 231 contratos 'perdidos' al usar precio_total_real\n";
echo "MOTIVO: precio_total_real incluye bono S/52,250, lo que triplica el umbral\n";
echo "Promedio total_price: S/26,754 → 5% = S/1,338\n";
echo "Promedio precio_total_real: S/80,232 → 5% = S/4,012\n";

echo "\n=== FIN ===\n";
