<?php

echo "Testing API middleware with real HTTP request...\n";

// Test the API endpoint
$url = 'http://127.0.0.1:8000/api/v1/security/users';

// First, let's test a simple endpoint to see if the server is working
echo "\n=== Test 1: Testing server connectivity ===\n";
$testUrl = 'http://127.0.0.1:8000/api/v1/security/login';
echo "Making request to: {$testUrl}\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => 'test', 'password' => 'test']));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "cURL Error: {$error}\n";
    echo "Server might not be running. Please check if 'php artisan serve' is running.\n";
    exit(1);
} else {
    echo "HTTP Status Code: {$httpCode}\n";
    echo "Response: {$response}\n";
    echo "✓ Server is responding\n";
}

// Now test with a user that we know has must_change_password = true
// We'll try to login with ltavaracastillo (password: 123456) to get a real token
echo "\n=== Test 2: Login with user that needs password change ===\n";
$loginData = [
    'username' => 'ltavaracastillo',
    'password' => '123456'
];

echo "Attempting login with user: ltavaracastillo\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "cURL Error: {$error}\n";
    exit(1);
}

echo "Login HTTP Status Code: {$httpCode}\n";
echo "Login Response: {$response}\n";

$loginResponse = json_decode($response, true);

if ($httpCode === 200 && isset($loginResponse['token'])) {
    $token = $loginResponse['token'];
    echo "✓ Login successful, got token\n";
    
    // Now test accessing a protected route with this token
    echo "\n=== Test 3: Access protected route with token ===\n";
    echo "Making request to: {$url}\n";
    
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "cURL Error: {$error}\n";
    } else {
        echo "HTTP Status Code: {$httpCode}\n";
        echo "Response: {$response}\n\n";
        
        if ($httpCode === 403) {
            $responseData = json_decode($response, true);
            if (isset($responseData['must_change_password']) && $responseData['must_change_password']) {
                echo "✓ SUCCESS: Middleware is working correctly!\n";
                echo "✓ User is blocked and required to change password\n";
            } else {
                echo "✗ Got 403 but not the expected must_change_password response\n";
            }
        } elseif ($httpCode === 200) {
            echo "✗ PROBLEM: User was allowed access without changing password\n";
            echo "✗ Middleware is NOT working correctly\n";
        } else {
            echo "? Unexpected status code: {$httpCode}\n";
        }
    }
} else {
    echo "✗ Login failed or user doesn't exist\n";
    echo "This might mean the user doesn't exist or password is wrong\n";
}

echo "\nDone.\n";