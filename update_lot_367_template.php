<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== ACTUALIZANDO TEMPLATE DEL LOTE 367 ===\n\n";

try {
    $template = LotFinancialTemplate::find(367);
    
    if ($template) {
        echo "Template encontrado:\n";
        echo "  - ID: {$template->id}\n";
        echo "  - Lot ID: {$template->lot_id}\n";
        echo "  - Precio lista: {$template->precio_lista}\n";
        echo "  - Precio venta: {$template->precio_venta}\n";
        echo "  - Cuota inicial: {$template->cuota_inicial}\n";
        echo "  - Installment 44 actual: {$template->installments_44}\n\n";
        
        // Calcular monto financiado
        $precioVenta = floatval($template->precio_venta ?? 28510.02);
        $cuotaInicial = floatval($template->cuota_inicial ?? 0);
        
        // Si la cuota inicial es 0, usar un valor por defecto del 10%
        if ($cuotaInicial == 0) {
            $cuotaInicial = round($precioVenta * 0.10, 2);
            echo "Cuota inicial era 0, usando 10% del precio de venta: {$cuotaInicial}\n";
        }
        
        $montoFinanciado = $precioVenta - $cuotaInicial;
        
        echo "Precio venta: {$precioVenta}\n";
        echo "Cuota inicial: {$cuotaInicial}\n";
        echo "Monto financiado: {$montoFinanciado}\n\n";
        
        // Calcular todos los installments
        $installment24 = round($montoFinanciado / 24, 2);
        $installment40 = round($montoFinanciado / 40, 2);
        $installment44 = round($montoFinanciado / 44, 2);
        $installment55 = round($montoFinanciado / 55, 2);
        
        $template->update([
            'cuota_inicial' => $cuotaInicial,
            'installments_24' => $installment24,
            'installments_40' => $installment40,
            'installments_44' => $installment44,
            'installments_55' => $installment55
        ]);
        
        echo "✅ Template actualizado exitosamente:\n";
        echo "  - Cuota inicial: {$cuotaInicial}\n";
        echo "  - Installment 24 meses: {$installment24}\n";
        echo "  - Installment 40 meses: {$installment40}\n";
        echo "  - Installment 44 meses: {$installment44}\n";
        echo "  - Installment 55 meses: {$installment55}\n\n";
        
        // Verificar la actualización
        $updatedTemplate = LotFinancialTemplate::find(367);
        echo "=== VERIFICACIÓN ===\n";
        echo "Template ID 367 después de actualización:\n";
        echo "  - Cuota inicial: {$updatedTemplate->cuota_inicial}\n";
        echo "  - Installment 24: {$updatedTemplate->installments_24}\n";
        echo "  - Installment 40: {$updatedTemplate->installments_40}\n";
        echo "  - Installment 44: {$updatedTemplate->installments_44}\n";
        echo "  - Installment 55: {$updatedTemplate->installments_55}\n";
        
    } else {
        echo "❌ Template con ID 367 no encontrado\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";