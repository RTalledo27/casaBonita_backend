<?php

require_once __DIR__ . '/bootstrap/app.php';

use Modules\Sales\app\Models\Lot;
use Modules\Sales\app\Models\LotFinancialTemplate;
use Modules\Sales\app\Models\Contract;
use Illuminate\Support\Facades\Log;

echo "=== DEBUG: Template Financiero durante Importación ===\n\n";

// 1. Verificar lotes con templates financieros
echo "1. Lotes con templates financieros:\n";
$lotsWithTemplates = Lot::with('financialTemplate')
    ->whereHas('financialTemplate')
    ->get();

foreach ($lotsWithTemplates as $lot) {
    $template = $lot->financialTemplate;
    echo "Lote: {$lot->lot_number} (Manzana: {$lot->block})\n";
    echo "  - Template ID: {$template->id}\n";
    echo "  - Precio Lista: {$template->precio_lista}\n";
    echo "  - Precio Venta: {$template->precio_venta}\n";
    echo "  - Cuota Inicial: {$template->cuota_inicial}\n";
    echo "  - Interest Rate: {$template->interest_rate}\n";
    echo "\n";
}

// 2. Simular búsqueda de lote como en la importación
echo "\n2. Simulando búsqueda de lote (como en importación):\n";

// Datos de ejemplo del Excel (usar datos reales de los logs)
$testData = [
    'lote_numero' => '1',
    'manzana' => 'A'
];

echo "Buscando lote: {$testData['lote_numero']}, Manzana: {$testData['manzana']}\n";

$lot = Lot::where('lot_number', $testData['lote_numero'])
    ->where('block', $testData['manzana'])
    ->with('financialTemplate')
    ->first();

if ($lot) {
    echo "✓ Lote encontrado: ID {$lot->lot_id}\n";
    echo "  - Precio del lote: {$lot->price}\n";
    
    $financialTemplate = $lot->financialTemplate;
    if ($financialTemplate) {
        echo "✓ Template financiero encontrado: ID {$financialTemplate->id}\n";
        echo "  - Precio Lista: {$financialTemplate->precio_lista}\n";
        echo "  - Precio Venta: {$financialTemplate->precio_venta}\n";
        echo "  - Cuota Inicial: {$financialTemplate->cuota_inicial}\n";
        echo "  - Interest Rate: {$financialTemplate->interest_rate}\n";
        
        // Simular la lógica de createDirectContract
        $totalPrice = $financialTemplate->precio_venta ?? $financialTemplate->precio_lista ?? $lot->price ?? 0;
        $downPayment = $financialTemplate->cuota_inicial ?? 0;
        $interestRate = $financialTemplate->interest_rate ?? 0;
        
        echo "\n  VALORES CALCULADOS (como en createDirectContract):\n";
        echo "  - Total Price: {$totalPrice}\n";
        echo "  - Down Payment: {$downPayment}\n";
        echo "  - Interest Rate: {$interestRate}\n";
        
    } else {
        echo "✗ NO se encontró template financiero para este lote\n";
        echo "  Se usarían valores por defecto:\n";
        echo "  - Total Price: " . ($lot->price ?? 100000) . "\n";
        echo "  - Down Payment: " . (($lot->price ?? 100000) * 0.20) . "\n";
    }
} else {
    echo "✗ Lote NO encontrado\n";
}

// 3. Verificar contratos recientes y sus valores
echo "\n\n3. Contratos recientes y sus valores financieros:\n";
$recentContracts = Contract::with(['lot', 'lot.financialTemplate'])
    ->orderBy('contract_id', 'desc')
    ->limit(3)
    ->get();

foreach ($recentContracts as $contract) {
    echo "Contrato ID: {$contract->contract_id}\n";
    echo "  - Lote: {$contract->lot->lot_number} (Manzana: {$contract->lot->block})\n";
    echo "  - Total Price (contrato): {$contract->total_price}\n";
    echo "  - Down Payment (contrato): {$contract->down_payment}\n";
    echo "  - Interest Rate (contrato): {$contract->interest_rate}\n";
    
    if ($contract->lot->financialTemplate) {
        $template = $contract->lot->financialTemplate;
        echo "  - Precio Venta (template): {$template->precio_venta}\n";
        echo "  - Cuota Inicial (template): {$template->cuota_inicial}\n";
        echo "  - Interest Rate (template): {$template->interest_rate}\n";
        
        // Verificar si coinciden
        $priceMatch = $contract->total_price == $template->precio_venta;
        $downMatch = $contract->down_payment == $template->cuota_inicial;
        $rateMatch = $contract->interest_rate == $template->interest_rate;
        
        echo "  COINCIDENCIAS:\n";
        echo "  - Precio: " . ($priceMatch ? '✓' : '✗') . "\n";
        echo "  - Cuota Inicial: " . ($downMatch ? '✓' : '✗') . "\n";
        echo "  - Interest Rate: " . ($rateMatch ? '✓' : '✗') . "\n";
    } else {
        echo "  - Sin template financiero\n";
    }
    echo "\n";
}

echo "\n=== FIN DEBUG ===\n";