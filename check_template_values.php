<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;

try {
    $template = LotFinancialTemplate::find(380);
    
    if ($template) {
        echo "=== TEMPLATE FINANCIERO ID 380 ===\n";
        echo "Precio lista: {$template->precio_lista}\n";
        echo "Descuento: {$template->descuento}\n";
        echo "Precio venta: {$template->precio_venta}\n";
        echo "Precio contado: {$template->precio_contado}\n";
        echo "Cuota inicial: {$template->cuota_inicial}\n";
        echo "Cuota balon: {$template->cuota_balon}\n";
        echo "Bono BPP: {$template->bono_bpp}\n";
        echo "CI fraccionamiento: {$template->ci_fraccionamiento}\n";
        echo "Installments 24: {$template->installments_24}\n";
        echo "Installments 40: {$template->installments_40}\n";
        echo "Installments 44: {$template->installments_44}\n";
        echo "Installments 55: {$template->installments_55}\n";
        echo "Installments 60: {$template->installments_60}\n";
        echo "Installments 72: {$template->installments_72}\n";
        echo "Installments 84: {$template->installments_84}\n";
        echo "Installments 96: {$template->installments_96}\n";
        echo "Installments 120: {$template->installments_120}\n";
        
        // Verificar si todos los valores están vacíos
        $isEmpty = empty($template->precio_lista) && 
                  empty($template->precio_venta) && 
                  empty($template->precio_contado) && 
                  empty($template->cuota_inicial);
        
        echo "\n¿Template vacío?: " . ($isEmpty ? 'SÍ' : 'NO') . "\n";
        
    } else {
        echo "Template 380 no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}