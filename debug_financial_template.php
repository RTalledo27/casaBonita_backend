<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Sales\Models\Contract;

echo "=== DEBUG FINANCIAL TEMPLATE MISMATCH ===\n\n";

// Buscar contratos recientes para verificar
$recentContracts = Contract::with(['lot', 'lot.financialTemplate'])
    ->orderBy('contract_id', 'desc')
    ->limit(3)
    ->get();

foreach ($recentContracts as $contract) {
    echo "--- CONTRATO ID: {$contract->contract_id} ---\n";
    echo "Lote ID: {$contract->lot_id}\n";
    echo "Total Price (Contrato): {$contract->total_price}\n";
    echo "Down Payment (Contrato): {$contract->down_payment}\n";
    echo "Interest Rate (Contrato): {$contract->interest_rate}\n";
    echo "Term Months (Contrato): {$contract->term_months}\n";
    
    if ($contract->lot) {
        echo "Lote Número: {$contract->lot->num_lot}\n";
        echo "Lote Price: {$contract->lot->price}\n";
        
        if ($contract->lot->financialTemplate) {
            $template = $contract->lot->financialTemplate;
            echo "\n--- TEMPLATE FINANCIERO ---\n";
            echo "Template ID: {$template->id}\n";
            echo "Precio Lista: {$template->precio_lista}\n";
            echo "Precio Venta: {$template->precio_venta}\n";
            echo "Cuota Inicial: {$template->cuota_inicial}\n";
            echo "Interest Rate: {$template->interest_rate}\n";
            
            // Verificar cuotas disponibles
            $installments = [];
            for ($i = 1; $i <= 24; $i++) {
                $field = "installment_{$i}";
                if (!is_null($template->$field) && $template->$field > 0) {
                    $installments[] = $template->$field;
                }
            }
            echo "Cuotas disponibles: " . count($installments) . "\n";
            if (!empty($installments)) {
                echo "Primera cuota: {$installments[0]}\n";
            }
            
            // Comparar valores
            echo "\n--- COMPARACIÓN ---\n";
            $expectedTotalPrice = $template->precio_venta ?? $template->precio_lista ?? $contract->lot->price ?? 0;
            $expectedDownPayment = $template->cuota_inicial ?? 0;
            
            echo "Total Price esperado: {$expectedTotalPrice}\n";
            echo "Total Price actual: {$contract->total_price}\n";
            echo "¿Coincide Total Price? " . ($expectedTotalPrice == $contract->total_price ? 'SÍ' : 'NO') . "\n";
            
            echo "Down Payment esperado: {$expectedDownPayment}\n";
            echo "Down Payment actual: {$contract->down_payment}\n";
            echo "¿Coincide Down Payment? " . ($expectedDownPayment == $contract->down_payment ? 'SÍ' : 'NO') . "\n";
            
        } else {
            echo "\n❌ LOTE SIN TEMPLATE FINANCIERO\n";
        }
    } else {
        echo "\n❌ CONTRATO SIN LOTE\n";
    }
    
    echo "\n" . str_repeat('=', 50) . "\n\n";
}

// Verificar si hay lotes con templates financieros
echo "\n=== VERIFICACIÓN DE TEMPLATES DISPONIBLES ===\n";
$lotsWithTemplates = Lot::with('financialTemplate')
    ->whereHas('financialTemplate')
    ->limit(5)
    ->get();

echo "Lotes con template financiero: " . $lotsWithTemplates->count() . "\n\n";

foreach ($lotsWithTemplates as $lot) {
    echo "Lote {$lot->num_lot} (ID: {$lot->lot_id}):\n";
    echo "  - Precio Lista: {$lot->financialTemplate->precio_lista}\n";
    echo "  - Precio Venta: {$lot->financialTemplate->precio_venta}\n";
    echo "  - Cuota Inicial: {$lot->financialTemplate->cuota_inicial}\n";
    echo "  - Interest Rate: {$lot->financialTemplate->interest_rate}\n";
}

echo "\n=== FIN DEBUG ===\n";