<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Get first user
    $user = \Modules\Security\Models\User::first();
    
    if (!$user) {
        echo "No users found in database\n";
        exit(1);
    }
    
    echo "User found: {$user->name} ({$user->email})\n";
    
    // Create token
    $token = $user->createToken('test-contracts-api')->plainTextToken;
    
    echo "Token generated: {$token}\n";
    
    // Test API call
    $url = 'http://localhost:8000/api/v1/sales/contracts?with_financing=true&per_page=10';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "\n=== API Test Results ===\n";
    echo "URL: {$url}\n";
    echo "HTTP Code: {$httpCode}\n";
    
    if ($error) {
        echo "cURL Error: {$error}\n";
    } else {
        echo "Response: {$response}\n";
        
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            echo "Contracts found: " . count($data['data']) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}