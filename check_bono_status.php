<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$total = DB::table('lot_financial_templates')->count();
$con52250 = DB::table('lot_financial_templates')->where('bono_techo_propio', 52250)->count();
$con51250 = DB::table('lot_financial_templates')->where('bono_techo_propio', 51250)->count();
$sinBono = DB::table('lot_financial_templates')->whereNull('bono_techo_propio')->count();
$otroBono = DB::table('lot_financial_templates')
    ->where('bono_techo_propio', '!=', 52250)
    ->where('bono_techo_propio', '!=', 51250)
    ->whereNotNull('bono_techo_propio')
    ->count();

echo "=== Estado del Bono Techo Propio ===\n";
echo "Total registros: {$total}\n";
echo "Con bono 52,250: {$con52250}\n";
echo "Con bono 51,250 (viejo): {$con51250}\n";
echo "Sin bono (NULL): {$sinBono}\n";
echo "Otro valor: {$otroBono}\n\n";

// Sample de los primeros 5
$samples = DB::table('lot_financial_templates')
    ->select('lot_id', 'precio_venta', 'bono_techo_propio', 'precio_total_real')
    ->limit(5)
    ->get();

echo "=== Primeros 5 registros ===\n";
foreach ($samples as $s) {
    $pct5 = $s->precio_total_real ? round($s->precio_total_real * 0.05, 2) : 'N/A';
    echo "Lot {$s->lot_id}: venta={$s->precio_venta}, bono={$s->bono_techo_propio}, total_real={$s->precio_total_real}, 5%={$pct5}\n";
}
