<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;
use Illuminate\Support\Facades\DB;
use Exception;

echo "=== PRUEBA DE BÚSQUEDA DE INSTALLMENTS POR LOTE ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Buscar algunos templates con datos de installments
    $templates = LotFinancialTemplate::whereRaw('(
        installments_24 > 0 OR 
        installments_40 > 0 OR 
        installments_44 > 0 OR 
        installments_55 > 0
    )')
    ->limit(5)
    ->get();
    
    if ($templates->isEmpty()) {
        echo "❌ No se encontraron templates con installments válidos\n";
        
        // Mostrar algunos templates para debug
        $allTemplates = LotFinancialTemplate::limit(3)->get();
        foreach ($allTemplates as $template) {
            echo "\nTemplate ID: {$template->id}, Lot ID: {$template->lot_id}\n";
            echo "  - installments_24: {$template->installments_24}\n";
            echo "  - installments_40: {$template->installments_40}\n";
            echo "  - installments_44: {$template->installments_44}\n";
            echo "  - installments_55: {$template->installments_55}\n";
        }
    } else {
        echo "✅ Encontrados {$templates->count()} templates con installments válidos\n\n";
        
        foreach ($templates as $template) {
            echo "--- Template ID: {$template->id}, Lot ID: {$template->lot_id} ---\n";
            
            // Simular la lógica de findFirstValidInstallment
            $installmentFields = [
                'installments_24' => 24,
                'installments_40' => 40,
                'installments_44' => 44,
                'installments_55' => 55
            ];
            
            $foundValid = false;
            foreach ($installmentFields as $field => $termMonths) {
                $amount = $template->{$field} ?? 0;
                echo "  {$field}: {$amount}";
                
                if ($amount > 0 && !$foundValid) {
                    echo " ← PRIMER VÁLIDO (term: {$termMonths} meses)";
                    $foundValid = true;
                }
                echo "\n";
            }
            
            if (!$foundValid) {
                echo "  ❌ No se encontró installment válido para este lote\n";
            }
            
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";