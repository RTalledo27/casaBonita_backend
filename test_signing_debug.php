<?php

require 'vendor/autoload.php';

$certPath = 'storage/certs/certificado.p12';
$password = 'Casabonita25';

if (!file_exists($certPath)) {
    echo "❌ Error: Cert file not found.\n";
    exit(1);
}

$p12Content = file_get_contents($certPath);
$certs = [];

echo "📂 Attempting to read P12...\n";
if (openssl_pkcs12_read($p12Content, $certs, $password)) {
    echo "✅ openssl_pkcs12_read SUCCESS.\n";
    
    $privateKey = $certs['pkey'];
    
    echo "🔑 Private Key Extracted (First 50 chars): " . substr($privateKey, 0, 50) . "...\n";
    
    // Test 1: openssl_pkey_get_private
    echo "\n🧪 Test 1: openssl_pkey_get_private...\n";
    $keyRes = openssl_pkey_get_private($privateKey);
    if ($keyRes === false) {
        echo "❌ openssl_pkey_get_private FAILED.\n";
        while ($msg = openssl_error_string()) echo "   Error: $msg\n";
    } else {
        echo "✅ openssl_pkey_get_private SUCCESS. Resource/Object obtained.\n";
        
        // Test 2: openssl_sign
        echo "\n🧪 Test 2: openssl_sign...\n";
        $data = "Test Data";
        $signature = '';
        if (openssl_sign($data, $signature, $keyRes, "SHA256")) {
             echo "✅ openssl_sign SUCCESS.\n";
        } else {
             echo "❌ openssl_sign FAILED.\n";
             while ($msg = openssl_error_string()) echo "   Error: $msg\n";
        }
    }

} else {
    echo "❌ openssl_pkcs12_read FAILED.\n";
    echo "   (This is unexpected as logs say it works now)\n";
    while ($msg = openssl_error_string()) echo "   Error: $msg\n";
}
