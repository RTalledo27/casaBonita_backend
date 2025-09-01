<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\app\Services\ContractImportService;
use Modules\Sales\Models\Contract;
use Modules\CRM\Models\Client;
use Modules\Inventory\Models\Lot;
use Modules\Collections\app\Models\LotFinancialTemplate;
use Modules\HumanResources\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "\n=== Test Direct Contract Fix - Verificando uso directo de LotFinancialTemplate ===\n";

try {
    // Obtener el último contrato creado
    $lastContract = Contract::orderBy('contract_id', 'desc')->first();
    
    if (!$lastContract) {
        echo "No se encontraron contratos en la base de datos.\n";
        exit(1);
    }
    
    echo "\nÚltimo contrato creado: ID {$lastContract->contract_id}\n";
    echo "Lote ID: {$lastContract->lot_id}\n";
    
    // Obtener el lote y su template financiero
    $lot = Lot::find($lastContract->lot_id);
    if (!$lot) {
        echo "ERROR: No se encontró el lote {$lastContract->lot_id}\n";
        exit(1);
    }
    
    $template = $lot->financialTemplate;
    if (!$template) {
        echo "ERROR: El lote {$lot->lot_id} no tiene template financiero\n";
        exit(1);
    }
    
    echo "\nTemplate financiero encontrado: ID {$template->id}\n";
    
    // Mostrar valores del template
    echo "\n=== VALORES DEL TEMPLATE FINANCIERO ===\n";
    echo "precio_venta: " . number_format($template->precio_venta, 2) . "\n";
    echo "cuota_inicial: " . number_format($template->cuota_inicial, 2) . "\n";
    echo "installments_24: " . number_format($template->installments_24, 2) . "\n";
    
    // Mostrar valores del contrato
    echo "\n=== VALORES DEL CONTRATO CREADO ===\n";
    echo "total_price: " . number_format($lastContract->total_price, 2) . "\n";
    echo "down_payment: " . number_format($lastContract->down_payment, 2) . "\n";
    echo "financing_amount: " . number_format($lastContract->financing_amount, 2) . "\n";
    echo "monthly_payment: " . number_format($lastContract->monthly_payment, 2) . "\n";
    echo "term_months: {$lastContract->term_months}\n";
    echo "interest_rate: {$lastContract->interest_rate}\n";
    
    // Verificar que los valores coincidan EXACTAMENTE
    echo "\n=== VERIFICACIÓN DE COINCIDENCIAS ===\n";
    
    $errors = [];
    
    // Verificar total_price = precio_venta
    if (abs($lastContract->total_price - $template->precio_venta) > 0.01) {
        $errors[] = "total_price ({$lastContract->total_price}) NO coincide con precio_venta ({$template->precio_venta})";
    } else {
        echo "✓ total_price coincide con precio_venta\n";
    }
    
    // Verificar down_payment = cuota_inicial
    if (abs($lastContract->down_payment - $template->cuota_inicial) > 0.01) {
        $errors[] = "down_payment ({$lastContract->down_payment}) NO coincide con cuota_inicial ({$template->cuota_inicial})";
    } else {
        echo "✓ down_payment coincide con cuota_inicial\n";
    }
    
    // Verificar monthly_payment = installments_24
    if (abs($lastContract->monthly_payment - $template->installments_24) > 0.01) {
        $errors[] = "monthly_payment ({$lastContract->monthly_payment}) NO coincide con installments_24 ({$template->installments_24})";
    } else {
        echo "✓ monthly_payment coincide con installments_24\n";
    }
    
    // Verificar financing_amount = precio_venta - cuota_inicial
    $expectedFinancingAmount = $template->precio_venta - $template->cuota_inicial;
    if (abs($lastContract->financing_amount - $expectedFinancingAmount) > 0.01) {
        $errors[] = "financing_amount ({$lastContract->financing_amount}) NO coincide con precio_venta - cuota_inicial ({$expectedFinancingAmount})";
    } else {
        echo "✓ financing_amount coincide con precio_venta - cuota_inicial\n";
    }
    
    // Verificar term_months = 24
    if ($lastContract->term_months != 24) {
        $errors[] = "term_months ({$lastContract->term_months}) NO es 24";
    } else {
        echo "✓ term_months es 24\n";
    }
    
    // Verificar interest_rate = 0
    if ($lastContract->interest_rate != 0) {
        $errors[] = "interest_rate ({$lastContract->interest_rate}) NO es 0";
    } else {
        echo "✓ interest_rate es 0\n";
    }
    
    if (empty($errors)) {
        echo "\n🎉 ÉXITO: Todos los valores financieros coinciden exactamente con el LotFinancialTemplate\n";
        echo "El método createDirectContract ahora usa ÚNICAMENTE valores directos del template.\n";
    } else {
        echo "\n❌ ERRORES ENCONTRADOS:\n";
        foreach ($errors as $error) {
            echo "- {$error}\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    exit(1);
}

echo "\n=== Fin de la verificación ===\n";