<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== VERIFICANDO LOTE 127 ===\n\n";

try {
    // Verificar si el lote existe
    $lot = Lot::find(127);
    
    if ($lot) {
        echo "✅ Lote 127 encontrado:\n";
        echo "  - Número: {$lot->numero}\n";
        echo "  - Manzana ID: {$lot->manzana_id}\n";
        echo "  - Estado: {$lot->estado}\n";
        echo "  - Precio: {$lot->precio}\n\n";
        
        // Verificar si tiene template financiero
        $template = LotFinancialTemplate::where('lot_id', 127)->first();
        
        if ($template) {
            echo "✅ Template financiero existe:\n";
            echo "  - Template ID: {$template->id}\n";
            echo "  - Precio lista: {$template->precio_lista}\n";
            echo "  - Precio venta: {$template->precio_venta}\n";
            echo "  - Cuota inicial: {$template->cuota_inicial}\n";
            echo "  - Installments 24: {$template->installments_24}\n";
            echo "  - Installments 40: {$template->installments_40}\n";
            echo "  - Installments 44: {$template->installments_44}\n";
            echo "  - Installments 55: {$template->installments_55}\n";
        } else {
            echo "❌ Template financiero NO existe para el lote 127\n";
            echo "\n=== CREANDO TEMPLATE FINANCIERO ===\n";
            
            // Crear template financiero basado en el precio del lote
            $precioVenta = floatval($lot->precio ?? 30000); // Usar precio del lote o valor por defecto
            $cuotaInicial = 0;
            $montoFinanciado = $precioVenta - $cuotaInicial;
            
            $newTemplate = LotFinancialTemplate::create([
                'lot_id' => 127,
                'precio_lista' => $precioVenta * 1.2, // 20% más que precio de venta
                'precio_venta' => $precioVenta,
                'precio_contado' => $precioVenta * 0.95, // 5% descuento por contado
                'cuota_inicial' => $cuotaInicial,
                'installments_24' => round($montoFinanciado / 24, 2),
                'installments_40' => round($montoFinanciado / 40, 2),
                'installments_44' => round($montoFinanciado / 44, 2),
                'installments_55' => round($montoFinanciado / 55, 2)
            ]);
            
            echo "✅ Template creado exitosamente:\n";
            echo "  - Template ID: {$newTemplate->id}\n";
            echo "  - Precio venta: {$newTemplate->precio_venta}\n";
            echo "  - Installment 40 meses: {$newTemplate->installments_40}\n";
        }
    } else {
        echo "❌ Lote 127 NO encontrado en la base de datos\n";
        echo "\n=== VERIFICANDO LOTES DISPONIBLES ===\n";
        
        // Mostrar algunos lotes disponibles
        $availableLots = Lot::orderBy('id', 'desc')->take(10)->get();
        echo "Últimos 10 lotes en la base de datos:\n";
        foreach ($availableLots as $availableLot) {
            echo "  - ID: {$availableLot->id}, Número: {$availableLot->numero}, Manzana: {$availableLot->manzana_id}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";