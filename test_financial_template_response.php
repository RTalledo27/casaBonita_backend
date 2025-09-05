<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== PROBANDO RESPUESTA DEL FINANCIAL TEMPLATE ===\n\n";

try {
    // Probar con el lote 367 que sabemos que tiene template
    $lot = Lot::with('financialTemplate')->find(367);
    
    if (!$lot) {
        echo "❌ Lote 367 no encontrado\n";
        exit(1);
    }
    
    $template = $lot->financialTemplate;
    
    if (!$template) {
        echo "❌ No se encontró template financiero\n";
        exit(1);
    }
    
    echo "✅ Template encontrado. Simulando respuesta del API:\n\n";
    
    // Simular exactamente lo que devuelve el endpoint
    $response = [
        'success' => true,
        'data' => $template->toArray()
    ];
    
    echo "Respuesta completa del API:\n";
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
    
    // Verificar los campos específicos que usa el frontend
    echo "=== CAMPOS ESPECÍFICOS PARA EL FRONTEND ===\n";
    echo "precio_venta: " . ($template->precio_venta ?? 'NULL') . "\n";
    echo "precio_lista: " . ($template->precio_lista ?? 'NULL') . "\n";
    echo "cuota_inicial: " . ($template->cuota_inicial ?? 'NULL') . "\n";
    echo "installments_24: " . ($template->installments_24 ?? 'NULL') . "\n";
    echo "installments_40: " . ($template->installments_40 ?? 'NULL') . "\n";
    echo "installments_44: " . ($template->installments_44 ?? 'NULL') . "\n";
    echo "installments_55: " . ($template->installments_55 ?? 'NULL') . "\n\n";
    
    // Simular la lógica del frontend para obtener installment
    echo "=== SIMULANDO LÓGICA DEL FRONTEND ===\n";
    
    $availableInstallments = [];
    
    if ($template->installments_24 && $template->installments_24 > 0) {
        $availableInstallments[] = ['months' => 24, 'amount' => (float)$template->installments_24];
    }
    if ($template->installments_40 && $template->installments_40 > 0) {
        $availableInstallments[] = ['months' => 40, 'amount' => (float)$template->installments_40];
    }
    if ($template->installments_44 && $template->installments_44 > 0) {
        $availableInstallments[] = ['months' => 44, 'amount' => (float)$template->installments_44];
    }
    if ($template->installments_55 && $template->installments_55 > 0) {
        $availableInstallments[] = ['months' => 55, 'amount' => (float)$template->installments_55];
    }
    
    echo "Installments disponibles: " . count($availableInstallments) . "\n";
    foreach ($availableInstallments as $inst) {
        echo "  - {$inst['months']} meses: {$inst['amount']}\n";
    }
    
    // Aplicar lógica de prioridad: 40, 44, 24, 55
    $priorityOrder = [40, 44, 24, 55];
    $selectedInstallment = null;
    
    foreach ($priorityOrder as $priorityMonths) {
        foreach ($availableInstallments as $inst) {
            if ($inst['months'] === $priorityMonths) {
                $selectedInstallment = $inst;
                break 2;
            }
        }
    }
    
    if (!$selectedInstallment && count($availableInstallments) > 0) {
        $selectedInstallment = $availableInstallments[0];
    }
    
    if ($selectedInstallment) {
        echo "\n✅ Installment seleccionado: {$selectedInstallment['months']} meses = {$selectedInstallment['amount']}\n";
    } else {
        echo "\n❌ No se pudo seleccionar ningún installment\n";
    }
    
    // Simular valores finales que debería tener el formulario
    echo "\n=== VALORES FINALES ESPERADOS EN EL FORMULARIO ===\n";
    echo "total_price: " . ($template->precio_venta ?: $template->precio_lista ?: 0) . "\n";
    echo "initial_payment: " . ($template->cuota_inicial ?: 0) . "\n";
    echo "term_months: " . ($selectedInstallment ? $selectedInstallment['months'] : 24) . "\n";
    echo "monthly_payment: " . ($selectedInstallment ? $selectedInstallment['amount'] : 0) . "\n";
    
    $totalPrice = $template->precio_venta ?: $template->precio_lista ?: 0;
    $initialPayment = $template->cuota_inicial ?: 0;
    $financedAmount = max(0, $totalPrice - $initialPayment);
    echo "financed_amount (calculado): {$financedAmount}\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}