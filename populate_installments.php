<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== POBLANDO INSTALLMENTS EN TEMPLATES FINANCIEROS ===\n\n";

try {
    // Obtener los primeros 10 templates que no tienen installments
    $templates = LotFinancialTemplate::where(function($query) {
        $query->whereNull('installments_24')
              ->orWhere('installments_24', 0)
              ->orWhere('installments_24', '');
    })
    ->whereNull('installments_40')
    ->whereNull('installments_44')
    ->whereNull('installments_55')
    ->take(10)
    ->get();
    
    echo "Templates encontrados sin installments: {$templates->count()}\n\n";
    
    foreach ($templates as $template) {
        echo "Actualizando template ID: {$template->id}, Lot ID: {$template->lot_id}\n";
        echo "  Precio venta: {$template->precio_venta}\n";
        echo "  Cuota inicial: {$template->cuota_inicial}\n";
        
        // Calcular monto financiado
        $precioVenta = floatval($template->precio_venta ?? 0);
        $cuotaInicial = floatval($template->cuota_inicial ?? 0);
        $montoFinanciado = $precioVenta - $cuotaInicial;
        
        if ($montoFinanciado > 0) {
            // Calcular installments para diferentes plazos (sin interés)
            $installment24 = round($montoFinanciado / 24, 2);
            $installment40 = round($montoFinanciado / 40, 2);
            $installment44 = round($montoFinanciado / 44, 2);
            $installment55 = round($montoFinanciado / 55, 2);
            
            // Actualizar el template
            $template->update([
                'installments_24' => $installment24,
                'installments_40' => $installment40,
                'installments_44' => $installment44,
                'installments_55' => $installment55
            ]);
            
            echo "  ✅ Actualizado:\n";
            echo "    - 24 meses: {$installment24}\n";
            echo "    - 40 meses: {$installment40}\n";
            echo "    - 44 meses: {$installment44}\n";
            echo "    - 55 meses: {$installment55}\n";
        } else {
            echo "  ❌ Monto financiado es 0 o negativo\n";
        }
        
        echo "  ---\n";
    }
    
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    
    // Verificar cuántos templates ahora tienen installments
    $templatesWithInstallments = LotFinancialTemplate::where(function($query) {
        $query->where('installments_24', '>', 0)
              ->orWhere('installments_40', '>', 0)
              ->orWhere('installments_44', '>', 0)
              ->orWhere('installments_55', '>', 0);
    })->count();
    
    echo "Templates con installments configurados: {$templatesWithInstallments}\n";
    
    // Mostrar un ejemplo
    $exampleTemplate = LotFinancialTemplate::where('installments_40', '>', 0)->first();
    if ($exampleTemplate) {
        echo "\nEjemplo de template configurado:\n";
        echo "  Lot ID: {$exampleTemplate->lot_id}\n";
        echo "  Precio venta: {$exampleTemplate->precio_venta}\n";
        echo "  Cuota inicial: {$exampleTemplate->cuota_inicial}\n";
        echo "  Installment 40 meses: {$exampleTemplate->installments_40}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN ===\n";