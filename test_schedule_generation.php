<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Get first user for authentication
    $user = \Modules\Security\Models\User::first();
    
    if (!$user) {
        echo "No users found in database\n";
        exit(1);
    }
    
    echo "User found: {$user->name} ({$user->email})\n";
    
    // Create token
    $token = $user->createToken('test-schedule-generation')->plainTextToken;
    echo "Token generated: {$token}\n";
    
    // Get first contract with financing
    $contract = \Modules\Sales\Models\Contract::where('financing_amount', '>', 0)->first();
    
    if (!$contract) {
        echo "No contracts with financing found\n";
        exit(1);
    }
    
    echo "\n=== Contract Details ===\n";
    echo "Contract ID: {$contract->contract_id}\n";
    echo "Contract Number: {$contract->contract_number}\n";
    echo "Financing Amount: {$contract->financing_amount}\n";
    echo "Term Months: {$contract->term_months}\n";
    echo "Interest Rate: {$contract->interest_rate}\n";
    
    // Test schedule generation API
    $url = "http://localhost:8000/api/v1/sales/contracts/{$contract->contract_id}/generate-schedule";
    
    $data = [
        'contract_id' => $contract->contract_id,
        'start_date' => date('Y-m-d', strtotime('+1 month')),
        'frequency' => 'monthly',
        'notes' => 'Cronograma generado automáticamente para pruebas'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "\n=== Schedule Generation Test ===\n";
    echo "URL: {$url}\n";
    echo "HTTP Code: {$httpCode}\n";
    echo "Request Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    
    if ($error) {
        echo "cURL Error: {$error}\n";
    } else {
        echo "Response: {$response}\n";
        
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['success']) && $responseData['success']) {
            echo "\n✅ Schedule generation successful!\n";
            echo "Total schedules created: " . $responseData['data']['total_schedules'] . "\n";
            echo "Total amount: " . $responseData['data']['total_amount'] . "\n";
            echo "Frequency: " . $responseData['data']['frequency'] . "\n";
        } else {
            echo "\n❌ Schedule generation failed\n";
            if (isset($responseData['message'])) {
                echo "Error message: " . $responseData['message'] . "\n";
            }
        }
    }
    
    // Test contracts listing API
    echo "\n=== Testing Contracts API ===\n";
    $contractsUrl = 'http://localhost:8000/api/v1/sales/contracts?with_financing=true&per_page=5';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $contractsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $contractsResponse = curl_exec($ch);
    $contractsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Contracts API HTTP Code: {$contractsHttpCode}\n";
    
    if ($contractsHttpCode === 200) {
        $contractsData = json_decode($contractsResponse, true);
        if ($contractsData && isset($contractsData['data'])) {
            echo "✅ Contracts API working - Found " . count($contractsData['data']) . " contracts\n";
        }
    } else {
        echo "❌ Contracts API failed\n";
        echo "Response: {$contractsResponse}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}