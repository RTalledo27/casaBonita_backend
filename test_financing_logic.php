<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== PRUEBA DE LÓGICA DE FINANCIAMIENTO ===\n\n";

// Buscar algunos lotes con diferentes configuraciones de installments
$lotsWithTemplates = DB::table('lots')
    ->join('lot_financial_templates', 'lots.lot_id', '=', 'lot_financial_templates.lot_id')
    ->select(
        'lots.lot_id',
        'lots.num_lot',
        'lot_financial_templates.installments_24',
        'lot_financial_templates.installments_40',
        'lot_financial_templates.installments_44',
        'lot_financial_templates.installments_55',
        'lot_financial_templates.precio_venta',
        'lot_financial_templates.cuota_inicial'
    )
    ->limit(15)
    ->get();

echo "Lotes encontrados con templates financieros: " . $lotsWithTemplates->count() . "\n\n";

$withFinancing = 0;
$withoutFinancing = 0;

foreach ($lotsWithTemplates as $lotData) {
    echo "--- LOTE {$lotData->num_lot} (ID: {$lotData->lot_id}) ---\n";
    echo "Precio venta: {$lotData->precio_venta}\n";
    echo "Cuota inicial: {$lotData->cuota_inicial}\n";
    echo "Installments:\n";
    echo "  - 24 meses: {$lotData->installments_24}\n";
    echo "  - 40 meses: {$lotData->installments_40}\n";
    echo "  - 44 meses: {$lotData->installments_44}\n";
    echo "  - 55 meses: {$lotData->installments_55}\n";
    
    // Determinar tipo de financiamiento según la nueva lógica
    $hasInstallments = false;
    $selectedInstallment = 0;
    $selectedTerm = 0;
    
    if ($lotData->installments_40 > 0) {
        $hasInstallments = true;
        $selectedInstallment = $lotData->installments_40;
        $selectedTerm = 40;
    } elseif ($lotData->installments_44 > 0) {
        $hasInstallments = true;
        $selectedInstallment = $lotData->installments_44;
        $selectedTerm = 44;
    } elseif ($lotData->installments_24 > 0) {
        $hasInstallments = true;
        $selectedInstallment = $lotData->installments_24;
        $selectedTerm = 24;
    } elseif ($lotData->installments_55 > 0) {
        $hasInstallments = true;
        $selectedInstallment = $lotData->installments_55;
        $selectedTerm = 55;
    }
    
    $financingType = $hasInstallments ? 'WITH_FINANCING' : 'WITHOUT_FINANCING';
    $financingAmount = $hasInstallments ? ($lotData->precio_venta - $lotData->cuota_inicial) : 0;
    
    if ($hasInstallments) {
        $withFinancing++;
    } else {
        $withoutFinancing++;
    }
    
    echo "RESULTADO:\n";
    echo "  - Tipo de financiamiento: {$financingType}\n";
    echo "  - Monto financiado: {$financingAmount}\n";
    echo "  - Cuota mensual: {$selectedInstallment}\n";
    echo "  - Plazo: {$selectedTerm} meses\n";
    echo "\n";
}

echo "RESUMEN DE LOTES:\n";
echo "- Con financiamiento: {$withFinancing}\n";
echo "- Sin financiamiento: {$withoutFinancing}\n\n";

// Buscar contratos existentes y verificar su clasificación
echo "=== VERIFICACIÓN DE CONTRATOS EXISTENTES ===\n\n";

$contractsQuery = DB::table('contracts')
    ->join('lots', 'contracts.lot_id', '=', 'lots.lot_id')
    ->leftJoin('lot_financial_templates', 'lots.lot_id', '=', 'lot_financial_templates.lot_id')
    ->select(
        'contracts.contract_id',
        'contracts.contract_number',
        'contracts.total_price',
        'contracts.down_payment',
        'contracts.financing_amount',
        'contracts.monthly_payment',
        'contracts.term_months',
        'lots.num_lot',
        'lot_financial_templates.installments_24',
        'lot_financial_templates.installments_40',
        'lot_financial_templates.installments_44',
        'lot_financial_templates.installments_55'
    )
    ->limit(15)
    ->get();

echo "Contratos encontrados: " . $contractsQuery->count() . "\n\n";

$contractsWithFinancing = 0;
$contractsWithoutFinancing = 0;
$correctClassification = 0;
$incorrectClassification = 0;

foreach ($contractsQuery as $contract) {
    echo "--- CONTRATO {$contract->contract_number} ---\n";
    echo "Lote: {$contract->num_lot}\n";
    echo "Precio total: {$contract->total_price}\n";
    echo "Cuota inicial: {$contract->down_payment}\n";
    echo "Monto financiado: {$contract->financing_amount}\n";
    echo "Cuota mensual: {$contract->monthly_payment}\n";
    echo "Plazo: {$contract->term_months} meses\n";
    
    $currentFinancingType = $contract->financing_amount > 0 ? 'WITH_FINANCING' : 'WITHOUT_FINANCING';
    echo "Tipo actual: {$currentFinancingType}\n";
    
    if ($currentFinancingType === 'WITH_FINANCING') {
        $contractsWithFinancing++;
    } else {
        $contractsWithoutFinancing++;
    }
    
    // Verificar si coincide con el template
    if ($contract->installments_24 !== null || $contract->installments_40 !== null || 
        $contract->installments_44 !== null || $contract->installments_55 !== null) {
        
        $templateHasInstallments = ($contract->installments_24 > 0 || 
                                   $contract->installments_40 > 0 || 
                                   $contract->installments_44 > 0 || 
                                   $contract->installments_55 > 0);
        $expectedType = $templateHasInstallments ? 'WITH_FINANCING' : 'WITHOUT_FINANCING';
        
        echo "Tipo esperado según template: {$expectedType}\n";
        echo "Template installments: 24={$contract->installments_24}, 40={$contract->installments_40}, 44={$contract->installments_44}, 55={$contract->installments_55}\n";
        
        $isCorrect = ($currentFinancingType === $expectedType);
        echo "¿Coincide?: " . ($isCorrect ? 'SÍ' : 'NO') . "\n";
        
        if ($isCorrect) {
            $correctClassification++;
        } else {
            $incorrectClassification++;
        }
    }
    
    echo "\n";
}

echo "RESUMEN DE CONTRATOS:\n";
echo "- Con financiamiento: {$contractsWithFinancing}\n";
echo "- Sin financiamiento: {$contractsWithoutFinancing}\n";
echo "- Clasificación correcta: {$correctClassification}\n";
echo "- Clasificación incorrecta: {$incorrectClassification}\n\n";

echo "=== PRUEBA COMPLETADA ===\n";