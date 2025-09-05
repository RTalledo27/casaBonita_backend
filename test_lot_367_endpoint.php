<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== PROBANDO ENDPOINT PARA LOTE 367 ===\n\n";

try {
    // Buscar el lote 367
    $lot = Lot::with('financialTemplate')->find(367);
    
    if (!$lot) {
        echo "❌ Lote 367 no encontrado\n";
        exit(1);
    }
    
    echo "✅ Lote encontrado:\n";
    echo "  - ID: {$lot->lot_id}\n";
    echo "  - Número: {$lot->num_lot}\n";
    echo "  - Manzana ID: {$lot->manzana_id}\n";
    echo "  - Estado: {$lot->status}\n\n";
    
    // Verificar la relación financialTemplate
    echo "=== VERIFICANDO RELACIÓN financialTemplate ===\n";
    $template = $lot->financialTemplate;
    
    if (!$template) {
        echo "❌ No se encontró template financiero usando la relación 'financialTemplate'\n";
        
        // Verificar si existe en la base de datos
        $directTemplate = LotFinancialTemplate::where('lot_id', 367)->first();
        if ($directTemplate) {
            echo "⚠️ Pero SÍ existe un template en la base de datos:\n";
            echo "  - Template ID: {$directTemplate->id}\n";
            echo "  - Precio venta: {$directTemplate->precio_venta}\n";
            echo "  - Cuota inicial: {$directTemplate->cuota_inicial}\n";
            echo "  - Installment 24: {$directTemplate->installments_24}\n";
            echo "  - Installment 40: {$directTemplate->installments_40}\n";
            echo "  - Installment 44: {$directTemplate->installments_44}\n";
            echo "  - Installment 55: {$directTemplate->installments_55}\n";
        } else {
            echo "❌ Tampoco existe template en la base de datos\n";
        }
    } else {
        echo "✅ Template financiero encontrado usando la relación 'financialTemplate':\n";
        echo "  - Template ID: {$template->id}\n";
        echo "  - Precio venta: {$template->precio_venta}\n";
        echo "  - Cuota inicial: {$template->cuota_inicial}\n";
        echo "  - Installment 24: {$template->installments_24}\n";
        echo "  - Installment 40: {$template->installments_40}\n";
        echo "  - Installment 44: {$template->installments_44}\n";
        echo "  - Installment 55: {$template->installments_55}\n\n";
        
        // Simular la respuesta del endpoint
        echo "=== RESPUESTA SIMULADA DEL ENDPOINT ===\n";
        $response = [
            'success' => true,
            'data' => $template->toArray()
        ];
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}