<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// No need for use statements, we'll use full class names

echo "=== Testing Financial Data Fix ===\n";

// Get a lot with financial template
$lot = \Modules\Sales\app\Models\Lot::with('financialTemplate')->first();
if (!$lot) {
    echo "No lots found in database\n";
    exit(1);
}

echo "Testing with Lot: {$lot->lot_number}\n";

if ($lot->financialTemplate) {
    echo "Financial Template found:\n";
    echo "- Total Price: {$lot->financialTemplate->total_price}\n";
    echo "- Down Payment: {$lot->financialTemplate->down_payment}\n";
    echo "- Financing Amount: {$lot->financialTemplate->financing_amount}\n";
    echo "- Monthly Payment: {$lot->financialTemplate->monthly_payment}\n";
    echo "- Term Months: {$lot->financialTemplate->term_months}\n";
    echo "- Interest Rate: {$lot->financialTemplate->interest_rate}\n";
} else {
    echo "No financial template found for this lot\n";
}

// Get a client and advisor for testing
$client = \Modules\Sales\app\Models\Client::first();
$advisor = \Modules\Sales\app\Models\Employee::where('position', 'Asesor')->first();

if (!$client || !$advisor) {
    echo "Missing client or advisor for testing\n";
    exit(1);
}

// Test data
$contractData = [
    'client_id' => $client->id,
    'lot_id' => $lot->id,
    'advisor_id' => $advisor->id,
    'sale_date' => now()->format('Y-m-d'),
    'status' => 'active',
    'currency' => 'USD'
];

echo "\n=== Testing createDirectContract ===\n";

try {
    $importService = new \Modules\Sales\app\Services\ContractImportService();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($importService);
    $method = $reflection->getMethod('createDirectContract');
    $method->setAccessible(true);
    
    $result = $method->invoke($importService, $contractData);
    
    if ($result['success']) {
        echo "Contract created successfully!\n";
        echo "Contract ID: {$result['contract_id']}\n";
        
        // Get the created contract to verify financial data
        $contract = \Modules\Sales\app\Models\Contract::find($result['contract_id']);
        
        echo "\n=== Financial Data Verification ===\n";
        echo "- Total Price: {$contract->total_price}\n";
        echo "- Down Payment: {$contract->down_payment}\n";
        echo "- Financing Amount: {$contract->financing_amount}\n";
        echo "- Monthly Payment: {$contract->monthly_payment}\n";
        echo "- Term Months: {$contract->term_months}\n";
        echo "- Interest Rate: {$contract->interest_rate}\n";
        
        // Compare with template if exists
        if ($lot->financialTemplate) {
            echo "\n=== Template vs Contract Comparison ===\n";
            echo "Total Price - Template: {$lot->financialTemplate->total_price}, Contract: {$contract->total_price}\n";
            echo "Down Payment - Template: {$lot->financialTemplate->down_payment}, Contract: {$contract->down_payment}\n";
            echo "Financing Amount - Template: {$lot->financialTemplate->financing_amount}, Contract: {$contract->financing_amount}\n";
            echo "Monthly Payment - Template: {$lot->financialTemplate->monthly_payment}, Contract: {$contract->monthly_payment}\n";
            echo "Term Months - Template: {$lot->financialTemplate->term_months}, Contract: {$contract->term_months}\n";
            echo "Interest Rate - Template: {$lot->financialTemplate->interest_rate}, Contract: {$contract->interest_rate}\n";
        }
        
    } else {
        echo "Contract creation failed: {$result['message']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";