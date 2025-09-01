<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;

try {
    // Buscar el lote 4 de la manzana I
    $manzana = Manzana::where('name', 'I')->first();
    
    if (!$manzana) {
        echo "Manzana 'I' no encontrada\n";
        exit(1);
    }
    
    echo "Manzana encontrada: ID {$manzana->manzana_id}, Nombre: {$manzana->name}\n";
    
    $lot = Lot::where('num_lot', 4)
              ->where('manzana_id', $manzana->manzana_id)
              ->with('financialTemplate')
              ->first();
    
    if (!$lot) {
        echo "Lote 4 no encontrado en manzana I\n";
        exit(1);
    }
    
    echo "Lote encontrado: ID {$lot->lot_id}, Número: {$lot->num_lot}\n";
    echo "Precio del lote: {$lot->price}\n";
    echo "Status: {$lot->status}\n";
    
    if ($lot->financialTemplate) {
        $template = $lot->financialTemplate;
        echo "\n=== TEMPLATE FINANCIERO ENCONTRADO ===\n";
        echo "Template ID: {$template->id}\n";
        echo "Down payment: {$template->down_payment}\n";
        echo "Interest rate: {$template->interest_rate}\n";
        echo "Total price: {$template->total_price}\n";
        
        // Mostrar algunas cuotas
        echo "\nCuotas (primeras 5):\n";
        for ($i = 1; $i <= 5; $i++) {
            $installment = $template->{'installment_' . $i};
            if ($installment > 0) {
                echo "Cuota {$i}: {$installment}\n";
            }
        }
    } else {
        echo "\n=== NO TIENE TEMPLATE FINANCIERO ===\n";
        echo "El lote no tiene un template financiero asociado\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}