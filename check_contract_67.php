<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFICACIÓN DEL CONTRATO 67 ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Obtener el contrato con consulta SQL directa
$contract = DB::table('contracts')->where('contract_id', 67)->first();

if (!$contract) {
    echo "❌ Contrato 67 no encontrado\n";
    exit;
}

echo "📋 DATOS DEL CONTRATO:\n";
echo "Contract ID: {$contract->contract_id}\n";
echo "Número: {$contract->contract_number}\n";
echo "Total Price: {$contract->total_price}\n";
echo "Down Payment: {$contract->down_payment}\n";
echo "Financing Amount: {$contract->financing_amount}\n";
echo "Monthly Payment: {$contract->monthly_payment}\n";
echo "Interest Rate: {$contract->interest_rate}\n";
echo "Term Months: {$contract->term_months}\n";
echo "Advisor ID: {$contract->advisor_id}\n";
echo "Lot ID: {$contract->lot_id}\n\n";

// Verificar el asesor
if ($contract->advisor_id) {
    $advisor = DB::table('users')->where('user_id', $contract->advisor_id)->first();
    echo "👤 ASESOR ASIGNADO:\n";
    echo "ID: {$advisor->user_id}\n";
    echo "Username: {$advisor->username}\n";
    echo "Nombre: {$advisor->first_name} {$advisor->last_name}\n";
    echo "Email: {$advisor->email}\n";
    echo "Posición: {$advisor->position}\n\n";
} else {
    echo "❌ NO HAY ASESOR ASIGNADO (advisor_id es NULL)\n\n";
}

// Verificar el lote y su template financiero
if ($contract->lot_id) {
    $lot = DB::table('lots')->where('lot_id', $contract->lot_id)->first();
    echo "🏠 DATOS DEL LOTE:\n";
    echo "ID: {$lot->lot_id}\n";
    echo "Número: {$lot->num_lot}\n";
    echo "Manzana ID: {$lot->manzana_id}\n";
    echo "Estado: {$lot->status}\n";
    echo "Precio Total: {$lot->total_price}\n\n";
    
    // Verificar template financiero
    $template = DB::table('lot_financial_templates')->where('lot_id', $lot->lot_id)->first();
    if ($template) {
        echo "💰 TEMPLATE FINANCIERO DEL LOTE:\n";
        echo "ID: {$template->id}\n";
        echo "Precio Lista: {$template->precio_lista}\n";
        echo "Precio Venta: {$template->precio_venta}\n";
        echo "Cuota Inicial: {$template->cuota_inicial}\n";
        echo "Precio Contado: {$template->precio_contado}\n";
        echo "Installments 24: {$template->installments_24}\n";
        echo "Installments 40: {$template->installments_40}\n";
        echo "Installments 44: {$template->installments_44}\n";
        echo "Installments 55: {$template->installments_55}\n\n";
        
        echo "🔍 COMPARACIÓN CONTRATO vs TEMPLATE:\n";
        echo "Total Price - Contrato: {$contract->total_price} | Template precio_venta: {$template->precio_venta}\n";
        echo "Down Payment - Contrato: {$contract->down_payment} | Template cuota_inicial: {$template->cuota_inicial}\n";
        echo "¿Coinciden totales? " . ($contract->total_price == $template->precio_venta ? '✅ SÍ' : '❌ NO') . "\n";
        echo "¿Coinciden enganches? " . ($contract->down_payment == $template->cuota_inicial ? '✅ SÍ' : '❌ NO') . "\n";
    } else {
        echo "❌ NO HAY TEMPLATE FINANCIERO PARA ESTE LOTE\n";
    }
}

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";