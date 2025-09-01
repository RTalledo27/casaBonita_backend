<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Obtener el último contrato creado
$lastContract = DB::table('contracts')
    ->orderBy('contract_id', 'desc')
    ->first();

if (!$lastContract) {
    file_put_contents('financial_check_result.txt', "No contracts found\n");
    exit(1);
}

$result = "ÚLTIMO CONTRATO CREADO:\n";
$result .= "ID: {$lastContract->contract_id}\n";
$result .= "Total Price: {$lastContract->total_price}\n";
$result .= "Down Payment: {$lastContract->down_payment}\n";
$result .= "Financing Amount: {$lastContract->financing_amount}\n";
$result .= "Monthly Payment: {$lastContract->monthly_payment}\n";
$result .= "Term Months: {$lastContract->term_months}\n";
$result .= "Interest Rate: {$lastContract->interest_rate}\n";
$result .= "Status: {$lastContract->status}\n";
$result .= "Contract ID: {$lastContract->contract_id}\n\n";

// Obtener el template financiero del lote
$lotTemplate = DB::table('lot_financial_templates')
    ->where('lot_id', $lastContract->lot_id)
    ->first();

if ($lotTemplate) {
    $result .= "TEMPLATE FINANCIERO DEL LOTE {$lastContract->lot_id}:\n";
    $result .= "Total Price: {$lotTemplate->total_price}\n";
    $result .= "Down Payment: {$lotTemplate->down_payment}\n";
    $result .= "Financing Amount: {$lotTemplate->financing_amount}\n";
    $result .= "Monthly Payment: {$lotTemplate->monthly_payment}\n";
    $result .= "Term Months: {$lotTemplate->term_months}\n";
    $result .= "Interest Rate: {$lotTemplate->interest_rate}\n\n";
    
    $result .= "COMPARACIÓN:\n";
    
    $fields = [
        'total_price' => 'Total Price',
        'down_payment' => 'Down Payment', 
        'financing_amount' => 'Financing Amount',
        'monthly_payment' => 'Monthly Payment',
        'term_months' => 'Term Months',
        'interest_rate' => 'Interest Rate'
    ];
    
    foreach ($fields as $field => $label) {
        $contractValue = $lastContract->$field;
        $templateValue = $lotTemplate->$field;
        
        if ($contractValue == $templateValue) {
            $result .= "✅ {$label}: {$contractValue} (MATCH)\n";
        } else {
            $result .= "❌ {$label}: Contract={$contractValue}, Template={$templateValue} (MISMATCH)\n";
        }
    }
    
    // Verificar específicamente la tasa de interés
    if ($lastContract->interest_rate == 0) {
        $result .= "\n✅ Interest Rate is 0 as requested\n";
    } else {
        $result .= "\n❌ Interest Rate should be 0, but is {$lastContract->interest_rate}\n";
    }
    
} else {
    $result .= "No financial template found for lot {$lastContract->lot_id}\n";
}

file_put_contents('financial_check_result.txt', $result);
echo "Results written to financial_check_result.txt\n";