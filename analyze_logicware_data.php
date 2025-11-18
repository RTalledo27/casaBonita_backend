<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ANÁLISIS DE DATOS LOGICWARE ===\n\n";

// Obtener un contrato con datos de Logicware
$contract = \Modules\Sales\Models\Contract::where('source', 'logicware')
    ->whereNotNull('logicware_data')
    ->first();

if (!$contract) {
    echo "❌ No se encontraron contratos con datos de Logicware\n";
    exit;
}

echo "📋 Contrato: {$contract->contract_number}\n";
echo "   Cliente: {$contract->getClientName()}\n";
echo "   Lote: {$contract->getLotName()}\n\n";

// Decodificar datos de Logicware
$logicwareData = json_decode($contract->logicware_data, true);

if (!$logicwareData) {
    echo "❌ Error decodificando JSON de Logicware\n";
    exit;
}

echo "🔍 ESTRUCTURA COMPLETA DE LOGICWARE:\n";
echo "=====================================\n\n";

// Mostrar estructura de financing
if (isset($logicwareData['financing'])) {
    echo "💰 FINANCING:\n";
    foreach ($logicwareData['financing'] as $key => $value) {
        if (is_array($value)) {
            echo "   - {$key}: " . json_encode($value) . "\n";
        } else {
            echo "   - {$key}: {$value}\n";
        }
    }
    echo "\n";
}

// Mostrar estructura de units
if (isset($logicwareData['units']) && is_array($logicwareData['units'])) {
    echo "🏠 UNITS (LOTE):\n";
    foreach ($logicwareData['units'][0] ?? [] as $key => $value) {
        if (is_array($value)) {
            echo "   - {$key}: " . json_encode($value) . "\n";
        } else {
            echo "   - {$key}: {$value}\n";
        }
    }
    echo "\n";
}

// Mostrar otros campos importantes
echo "📊 OTROS CAMPOS:\n";
$importantFields = ['correlative', 'saleStartDate', 'proformaStartDate', 'seller', 'documentNumber'];
foreach ($importantFields as $field) {
    if (isset($logicwareData[$field])) {
        echo "   - {$field}: {$logicwareData[$field]}\n";
    }
}
echo "\n";

echo "📦 DATOS ACTUALES EN CONTRATO:\n";
echo "   - Total Price: S/ " . number_format($contract->total_price, 2) . "\n";
echo "   - Down Payment: S/ " . number_format($contract->down_payment, 2) . "\n";
echo "   - Financing Amount: S/ " . number_format($contract->financing_amount, 2) . "\n";
echo "   - Balloon Payment: S/ " . number_format($contract->balloon_payment ?? 0, 2) . "\n";
echo "   - BPP: S/ " . number_format($contract->bpp, 2) . "\n";
echo "   - BFH: S/ " . number_format($contract->bfh, 2) . "\n";
echo "   - Monthly Payment: S/ " . number_format($contract->monthly_payment, 2) . "\n";
echo "   - Term Months: {$contract->term_months}\n\n";

echo "🔎 CAMPOS DISPONIBLES EN FINANCING DE LOGICWARE:\n";
if (isset($logicwareData['financing'])) {
    $financing = $logicwareData['financing'];
    
    // Buscar campos relacionados con descuento
    $discountFields = array_filter(array_keys($financing), function($key) {
        return stripos($key, 'discount') !== false || 
               stripos($key, 'descuento') !== false ||
               stripos($key, 'bono') !== false ||
               stripos($key, 'balon') !== false ||
               stripos($key, 'balloon') !== false;
    });
    
    if (!empty($discountFields)) {
        echo "   📍 Campos de descuento/bono encontrados:\n";
        foreach ($discountFields as $field) {
            echo "      - {$field}: {$financing[$field]}\n";
        }
    } else {
        echo "   ⚠️ No se encontraron campos obvios de descuento/bono\n";
    }
}

echo "\n✅ Análisis completado\n";
echo "\n💡 TODOS LOS CAMPOS DE FINANCING:\n";
if (isset($logicwareData['financing'])) {
    print_r($logicwareData['financing']);
}
