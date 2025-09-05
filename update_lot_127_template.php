<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== ACTUALIZANDO TEMPLATE DEL LOTE 127 ===\n\n";

try {
    $template = LotFinancialTemplate::find(127);
    
    if ($template) {
        echo "Template encontrado:\n";
        echo "  - ID: {$template->id}\n";
        echo "  - Lot ID: {$template->lot_id}\n";
        echo "  - Precio venta: {$template->precio_venta}\n";
        echo "  - Cuota inicial: {$template->cuota_inicial}\n\n";
        
        // Calcular monto financiado
        $precioVenta = floatval($template->precio_venta ?? 28510.02);
        $cuotaInicial = floatval($template->cuota_inicial ?? 3000);
        $montoFinanciado = $precioVenta - $cuotaInicial;
        
        echo "Monto financiado: {$montoFinanciado}\n\n";
        
        // Actualizar con installments calculados
        $installment24 = round($montoFinanciado / 24, 2);
        $installment40 = round($montoFinanciado / 40, 2);
        $installment44 = round($montoFinanciado / 44, 2);
        $installment55 = round($montoFinanciado / 55, 2);
        
        $template->update([
            'installments_24' => $installment24,
            'installments_40' => $installment40,
            'installments_44' => $installment44,
            'installments_55' => $installment55
        ]);
        
        echo "✅ Template actualizado exitosamente:\n";
        echo "  - Installment 24 meses: {$installment24}\n";
        echo "  - Installment 40 meses: {$installment40}\n";
        echo "  - Installment 44 meses: {$installment44}\n";
        echo "  - Installment 55 meses: {$installment55}\n\n";
        
        // Verificar la actualización
        $updatedTemplate = LotFinancialTemplate::find(127);
        echo "=== VERIFICACIÓN ===\n";
        echo "Template ID 127 después de actualización:\n";
        echo "  - Installment 24: {$updatedTemplate->installments_24}\n";
        echo "  - Installment 40: {$updatedTemplate->installments_40}\n";
        echo "  - Installment 44: {$updatedTemplate->installments_44}\n";
        echo "  - Installment 55: {$updatedTemplate->installments_55}\n";
        
    } else {
        echo "❌ Template con ID 127 no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";