<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\Inventory\Models\Lot;

echo "=== VERIFICACIÓN DE TEMPLATES FINANCIEROS ===\n\n";

// Revisar los 3 contratos de DANIELA
$contractIds = [4, 30, 42];

foreach ($contractIds as $contractId) {
    echo "--- CONTRATO {$contractId} ---\n";
    
    $contract = Contract::with(['lot.financialTemplate'])->find($contractId);
    
    if (!$contract) {
        echo "❌ Contrato no encontrado\n\n";
        continue;
    }
    
    echo "Monto contrato: S/ " . number_format($contract->financing_amount, 2) . "\n";
    echo "Plazo contrato: {$contract->term_months} meses\n";
    
    if ($contract->lot) {
        echo "Lote ID: {$contract->lot->lot_id}\n";
        
        if ($contract->lot->financialTemplate) {
            $template = $contract->lot->financialTemplate;
            echo "✅ Template encontrado:\n";
            echo "  - Template ID: {$template->id}\n";
            echo "  - Monto template: S/ " . number_format($template->financing_amount, 2) . "\n";
            echo "  - Plazo template: {$template->term_months} meses\n";
            echo "  - Versión: {$template->version}\n";
            
            // Verificar si los montos coinciden
            if ($template->financing_amount != $contract->financing_amount) {
                echo "⚠️  DISCREPANCIA: Monto template (" . number_format($template->financing_amount, 2) . ") != Monto contrato (" . number_format($contract->financing_amount, 2) . ")\n";
            }
            
            if ($template->term_months != $contract->term_months) {
                echo "⚠️  DISCREPANCIA: Plazo template ({$template->term_months}) != Plazo contrato ({$contract->term_months})\n";
            }
        } else {
            echo "❌ Sin template financiero\n";
        }
    } else {
        echo "❌ Sin lote asociado\n";
    }
    
    echo "\n";
}

echo "=== VERIFICACIÓN DE COMISIONES EXISTENTES ===\n\n";

// Revisar las comisiones existentes para entender de dónde vienen los montos
use Modules\HumanResources\Models\Commission;

$commissions = Commission::where('employee_id', 7)
    ->where('period_month', 6)
    ->where('period_year', 2025)
    ->orderBy('contract_id')
    ->orderBy('parent_commission_id')
    ->get();

foreach ($commissions as $commission) {
    echo "Comisión ID: {$commission->commission_id}\n";
    echo "Contrato: {$commission->contract_id}\n";
    echo "Monto venta: S/ " . number_format($commission->sale_amount, 2) . "\n";
    echo "Porcentaje: {$commission->commission_percentage}%\n";
    echo "Monto comisión: S/ " . number_format($commission->commission_amount, 2) . "\n";
    echo "Tipo: " . ($commission->parent_commission_id ? 'HIJA' : 'PADRE') . "\n";
    echo "Pagable: " . ($commission->is_payable ? 'SÍ' : 'NO') . "\n";
    echo "---\n";
}