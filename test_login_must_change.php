<?php

echo "🧪 Testing Login with must_change_password\n";
echo "===========================================================\n\n";

// Test login with a user that must change password
$url = 'http://localhost:8000/v1/security/login';

$data = [
    'username' => 'jrondoytalledo',
    'password' => 'Casabonita2024'  // Contraseña por defecto
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

echo "📤 Testing POST {$url}\n";
echo "Username: {$data['username']}\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
echo "-----------------------------------------------------------\n";

if ($httpCode == 200) {
    echo "✅ Login Success!\n\n";
    
    $result = json_decode($response, true);
    
    if ($result) {
        echo "📊 Response:\n";
        
        // Check user object
        if (isset($result['user'])) {
            echo "\n👤 User object:\n";
            echo "  - ID: {$result['user']['id']}\n";
            echo "  - Username: {$result['user']['username']}\n";
            echo "  - Name: {$result['user']['name']}\n";
            
            // The important field
            if (isset($result['user']['must_change_password'])) {
                $mustChange = $result['user']['must_change_password'] ? '⚠️ YES' : '✅ NO';
                echo "  - Must Change Password: {$mustChange}\n";
            } else {
                echo "  - ❌ Must Change Password field NOT FOUND in user object\n";
            }
        }
        
        // Also check root level
        if (isset($result['must_change_password'])) {
            $mustChange = $result['must_change_password'] ? '⚠️ YES' : '✅ NO';
            echo "\n🔐 Root level must_change_password: {$mustChange}\n";
        }
        
        echo "\n📄 Full response:\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ Login Failed!\n";
    echo "Response: {$response}\n";
}

echo "\n===========================================================\n";
