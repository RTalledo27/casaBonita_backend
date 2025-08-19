<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Obtener el último contrato
$contract = DB::table('contracts')->orderBy('contract_id', 'desc')->first();

if (!$contract) {
    echo "No contracts found.\n";
    exit;
}

echo "=== LAST CONTRACT FINANCIAL DATA ===\n";
echo "Contract ID: {$contract->contract_id}\n";
echo "Total Price: {$contract->total_price}\n";
echo "Down Payment: {$contract->down_payment}\n";
echo "Financing Amount: {$contract->financing_amount}\n";
echo "Monthly Payment: {$contract->monthly_payment}\n";
echo "Term Months: {$contract->term_months}\n";
echo "Interest Rate: {$contract->interest_rate}\n";
echo "Lot ID: {$contract->lot_id}\n\n";

// Obtener el template del lote
$template = DB::table('lot_financial_templates')->where('lot_id', $contract->lot_id)->first();

if ($template) {
    echo "=== LOT FINANCIAL TEMPLATE ===\n";
    echo "Template Total Price: {$template->total_price}\n";
    echo "Template Down Payment: {$template->down_payment}\n";
    echo "Template Financing Amount: {$template->financing_amount}\n";
    echo "Template Monthly Payment: {$template->monthly_payment}\n";
    echo "Template Term Months: {$template->term_months}\n";
    echo "Template Interest Rate: {$template->interest_rate}\n\n";
    
    echo "=== COMPARISON ===\n";
    echo "Total Price Match: " . ($contract->total_price == $template->total_price ? 'YES' : 'NO') . "\n";
    echo "Down Payment Match: " . ($contract->down_payment == $template->down_payment ? 'YES' : 'NO') . "\n";
    echo "Financing Amount Match: " . ($contract->financing_amount == $template->financing_amount ? 'YES' : 'NO') . "\n";
    echo "Monthly Payment Match: " . ($contract->monthly_payment == $template->monthly_payment ? 'YES' : 'NO') . "\n";
    echo "Term Months Match: " . ($contract->term_months == $template->term_months ? 'YES' : 'NO') . "\n";
    echo "Interest Rate Match: " . ($contract->interest_rate == $template->interest_rate ? 'YES' : 'NO') . "\n";
    echo "Interest Rate is 0: " . ($contract->interest_rate == 0 ? 'YES' : 'NO') . "\n";
} else {
    echo "No financial template found for lot {$contract->lot_id}\n";
}