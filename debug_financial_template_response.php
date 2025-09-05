<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== DEBUG FINANCIAL TEMPLATE RESPONSE ===\n\n";

// Buscar un lote con template financiero
$lot = Lot::with('financialTemplate')->whereHas('financialTemplate')->first();

if ($lot && $lot->financialTemplate) {
    echo "✅ Lote encontrado: ID {$lot->lot_id}\n";
    echo "Número de lote: {$lot->num_lot}\n";
    echo "Precio del lote: {$lot->price}\n\n";
    
    $template = $lot->financialTemplate;
    echo "=== DATOS DEL FINANCIAL TEMPLATE ===\n";
    echo "Template ID: {$template->id}\n";
    echo "Precio Lista: {$template->precio_lista}\n";
    echo "Precio Venta: {$template->precio_venta}\n";
    echo "Cuota Inicial: {$template->cuota_inicial}\n";
    echo "Interest Rate: {$template->interest_rate}\n";
    echo "Installments 24: {$template->installments_24}\n";
    echo "Installments 40: {$template->installments_40}\n";
    echo "Installments 44: {$template->installments_44}\n";
    echo "Installments 55: {$template->installments_55}\n\n";
    
    // Simular la respuesta del endpoint
    echo "=== RESPUESTA SIMULADA DEL ENDPOINT ===\n";
    $response = [
        'precio_lista' => $template->precio_lista,
        'precio_venta' => $template->precio_venta,
        'cuota_inicial' => $template->cuota_inicial,
        'interest_rate' => $template->interest_rate,
        'installments_24' => $template->installments_24,
        'installments_40' => $template->installments_40,
        'installments_44' => $template->installments_44,
        'installments_55' => $template->installments_55
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
    
    // Verificar cuál installment debería usarse por defecto
    echo "=== LÓGICA DE TERM_MONTHS ===\n";
    if ($template->installments_40 > 0) {
        echo "✅ Usar installments_40: {$template->installments_40} (40 meses)\n";
    } elseif ($template->installments_44 > 0) {
        echo "✅ Usar installments_44: {$template->installments_44} (44 meses)\n";
    } elseif ($template->installments_24 > 0) {
        echo "✅ Usar installments_24: {$template->installments_24} (24 meses)\n";
    } elseif ($template->installments_55 > 0) {
        echo "✅ Usar installments_55: {$template->installments_55} (55 meses)\n";
    } else {
        echo "❌ No hay installments configurados\n";
    }
    
} else {
    echo "❌ No se encontró ningún lote con template financiero\n";
    
    // Verificar cuántos lotes y templates existen
    $totalLots = Lot::count();
    $totalTemplates = LotFinancialTemplate::count();
    
    echo "Total de lotes: {$totalLots}\n";
    echo "Total de templates: {$totalTemplates}\n";
}

echo "\n=== FIN DEBUG ===\n";