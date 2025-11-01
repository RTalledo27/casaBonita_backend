<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// First, login to get token
$loginUrl = 'http://localhost:8000/api/v1/security/login';
$loginData = json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login HTTP Code: $loginHttpCode\n";

if ($loginHttpCode === 200) {
    $loginData = json_decode($loginResponse, true);
    $token = $loginData['token'];
    echo "Login successful! Token: $token\n\n";
    
    // Now test the protected API endpoint
    $apiUrl = 'http://localhost:8000/api/v1/sales/contracts/with-financing?per_page=1000';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $apiResponse = curl_exec($ch);
    $apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "API HTTP Code: $apiHttpCode\n";
    echo "API Response: " . substr($apiResponse, 0, 500) . "...\n";
    
    if ($apiHttpCode === 200) {
        echo "✅ API call successful!\n";
    } else {
        echo "❌ API call failed\n";
    }
} else {
    echo "❌ Login failed: $loginResponse\n";
}