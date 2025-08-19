<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== VERIFICANDO TEMPLATES FINANCIEROS ===\n\n";

try {
    $lot1 = Lot::find(1);
    $lot2 = Lot::find(2);
    
    if ($lot1) {
        echo "Lote 1 encontrado - Número: {$lot1->num_lot}, Manzana ID: {$lot1->manzana_id}\n";
        $template1 = $lot1->lotFinancialTemplate;
        echo "Template financiero: " . ($template1 ? 'SÍ' : 'NO') . "\n";
        
        if (!$template1) {
            echo "Creando template financiero para lote 1...\n";
            LotFinancialTemplate::create([
                'lot_id' => $lot1->lot_id,
                'precio_lista' => 60000,
                'descuento' => 10000,
                'precio_venta' => 50000,
                'precio_contado' => 45000,
                'cuota_balon' => 0,
                'bono_bpp' => 0,
                'cuota_inicial' => 10000,
                'ci_fraccionamiento' => 0,
                'installments_24' => 2000,
                'installments_36' => 1400,
                'installments_48' => 1100,
                'installments_60' => 900,
                'installments_72' => 800,
                'installments_84' => 700,
                'installments_96' => 650,
                'installments_108' => 600,
                'installments_120' => 550,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "Template creado para lote 1\n";
        }
    } else {
        echo "Lote 1 no encontrado\n";
    }
    
    echo "\n";
    
    if ($lot2) {
        echo "Lote 2 encontrado - Número: {$lot2->num_lot}, Manzana ID: {$lot2->manzana_id}\n";
        $template2 = $lot2->lotFinancialTemplate;
        echo "Template financiero: " . ($template2 ? 'SÍ' : 'NO') . "\n";
        
        if (!$template2) {
            echo "Creando template financiero para lote 2...\n";
            LotFinancialTemplate::create([
                'lot_id' => $lot2->lot_id,
                'precio_lista' => 55000,
                'descuento' => 10000,
                'precio_venta' => 45000,
                'precio_contado' => 40000,
                'cuota_balon' => 0,
                'bono_bpp' => 0,
                'cuota_inicial' => 9000,
                'ci_fraccionamiento' => 0,
                'installments_24' => 1800,
                'installments_36' => 1200,
                'installments_48' => 1000,
                'installments_60' => 850,
                'installments_72' => 750,
                'installments_84' => 650,
                'installments_96' => 600,
                'installments_108' => 550,
                'installments_120' => 500,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "Template creado para lote 2\n";
        }
    } else {
        echo "Lote 2 no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";