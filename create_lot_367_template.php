<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== VERIFICANDO Y CREANDO TEMPLATE PARA LOTE 367 ===\n\n";

try {
    // Verificar si el lote existe
    $lot = Lot::find(367);
    
    if (!$lot) {
        echo "❌ Lote 367 no encontrado\n";
        exit(1);
    }
    
    echo "✅ Lote encontrado:\n";
    echo "  - ID: {$lot->id}\n";
    echo "  - Número: {$lot->lot_number}\n";
    echo "  - Manzana ID: {$lot->manzana_id}\n";
    echo "  - Estado: {$lot->status}\n\n";
    
    // Verificar si ya tiene template financiero
    $existingTemplate = LotFinancialTemplate::where('lot_id', 367)->first();
    
    if ($existingTemplate) {
        echo "✅ Template financiero ya existe (ID: {$existingTemplate->id})\n";
        echo "  - Precio lista: {$existingTemplate->precio_lista}\n";
        echo "  - Precio venta: {$existingTemplate->precio_venta}\n";
        echo "  - Cuota inicial: {$existingTemplate->cuota_inicial}\n";
        echo "  - Installment 24: {$existingTemplate->installments_24}\n";
        echo "  - Installment 40: {$existingTemplate->installments_40}\n";
        echo "  - Installment 44: {$existingTemplate->installments_44}\n";
        echo "  - Installment 55: {$existingTemplate->installments_55}\n";
    } else {
        echo "⚠️ Template financiero no existe. Creando uno nuevo...\n\n";
        
        // Valores por defecto basados en otros lotes similares
        $precioLista = 35000.00;
        $precioVenta = 30000.00;
        $cuotaInicial = 3500.00;
        $montoFinanciado = $precioVenta - $cuotaInicial;
        
        // Calcular installments
        $installment24 = round($montoFinanciado / 24, 2);
        $installment40 = round($montoFinanciado / 40, 2);
        $installment44 = round($montoFinanciado / 44, 2);
        $installment55 = round($montoFinanciado / 55, 2);
        
        $template = LotFinancialTemplate::create([
            'lot_id' => 367,
            'precio_lista' => $precioLista,
            'precio_venta' => $precioVenta,
            'cuota_inicial' => $cuotaInicial,
            'installments_24' => $installment24,
            'installments_40' => $installment40,
            'installments_44' => $installment44,
            'installments_55' => $installment55
        ]);
        
        echo "✅ Template financiero creado exitosamente:\n";
        echo "  - ID: {$template->id}\n";
        echo "  - Precio lista: {$template->precio_lista}\n";
        echo "  - Precio venta: {$template->precio_venta}\n";
        echo "  - Cuota inicial: {$template->cuota_inicial}\n";
        echo "  - Monto financiado: {$montoFinanciado}\n";
        echo "  - Installment 24 meses: {$template->installments_24}\n";
        echo "  - Installment 40 meses: {$template->installments_40}\n";
        echo "  - Installment 44 meses: {$template->installments_44}\n";
        echo "  - Installment 55 meses: {$template->installments_55}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";