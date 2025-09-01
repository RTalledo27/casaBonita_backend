<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== VERIFICACIÓN DE DATOS FINANCIEROS ===\n\n";

// Obtener el último contrato creado
$lastContract = DB::table('contracts')
    ->orderBy('created_at', 'desc')
    ->first();

if (!$lastContract) {
    echo "❌ No se encontraron contratos en la base de datos\n";
    exit(1);
}

echo "📋 Último contrato creado:\n";
echo "   ID: {$lastContract->contract_id}\n";
echo "   Cliente ID: {$lastContract->client_id}\n";
echo "   Lote ID: {$lastContract->lot_id}\n";
echo "   Precio Total: {$lastContract->total_price}\n";
echo "   Cuota Inicial: {$lastContract->down_payment}\n";
echo "   Monto Financiado: {$lastContract->financing_amount}\n";
echo "   Pago Mensual: {$lastContract->monthly_payment}\n";
echo "   Plazo (meses): {$lastContract->term_months}\n";
echo "   Tasa de Interés: {$lastContract->interest_rate}\n";
echo "   Estado: {$lastContract->status}\n";
echo "   Creado: {$lastContract->created_at}\n\n";

// Obtener el template financiero del lote
$lotTemplate = DB::table('lot_financial_templates')
    ->where('lot_id', $lastContract->lot_id)
    ->first();

if ($lotTemplate) {
    echo "💰 Template financiero del lote {$lastContract->lot_id}:\n";
    echo "   Precio Total: {$lotTemplate->total_price}\n";
    echo "   Cuota Inicial: {$lotTemplate->down_payment}\n";
    echo "   Monto Financiado: {$lotTemplate->financing_amount}\n";
    echo "   Pago Mensual: {$lotTemplate->monthly_payment}\n";
    echo "   Plazo (meses): {$lotTemplate->term_months}\n";
    echo "   Tasa de Interés: {$lotTemplate->interest_rate}\n\n";
    
    // Comparar datos
    echo "🔍 COMPARACIÓN DE DATOS:\n";
    
    $matches = [];
    $mismatches = [];
    
    $fields = [
        'total_price' => 'Precio Total',
        'down_payment' => 'Cuota Inicial', 
        'financing_amount' => 'Monto Financiado',
        'monthly_payment' => 'Pago Mensual',
        'term_months' => 'Plazo (meses)',
        'interest_rate' => 'Tasa de Interés'
    ];
    
    foreach ($fields as $field => $label) {
        $contractValue = $lastContract->$field;
        $templateValue = $lotTemplate->$field;
        
        if ($contractValue == $templateValue) {
            $matches[] = "   ✅ {$label}: {$contractValue} (coincide)";
        } else {
            $mismatches[] = "   ❌ {$label}: Contrato={$contractValue}, Template={$templateValue}";
        }
    }
    
    foreach ($matches as $match) {
        echo $match . "\n";
    }
    
    foreach ($mismatches as $mismatch) {
        echo $mismatch . "\n";
    }
    
    // Verificar específicamente la tasa de interés
    if ($lastContract->interest_rate == 0) {
        echo "\n✅ CORRECTO: La tasa de interés es 0 como se solicitó\n";
    } else {
        echo "\n❌ ERROR: La tasa de interés debería ser 0, pero es {$lastContract->interest_rate}\n";
    }
    
} else {
    echo "⚠️  No se encontró template financiero para el lote {$lastContract->lot_id}\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n";