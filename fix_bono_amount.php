<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$count = DB::table('lot_financial_templates')
    ->where('bono_techo_propio', 51250)
    ->update([
        'bono_techo_propio' => 52250,
        'precio_total_real' => DB::raw('precio_venta + 52250')
    ]);

echo "Actualizados: {$count} registros\n";

$sample = DB::table('lot_financial_templates')
    ->select('lot_id', 'precio_venta', 'bono_techo_propio', 'precio_total_real')
    ->first();

echo "Sample: precio_venta={$sample->precio_venta}, bono={$sample->bono_techo_propio}, total_real={$sample->precio_total_real}\n";
echo "5% = " . ($sample->precio_total_real * 0.05) . "\n";
