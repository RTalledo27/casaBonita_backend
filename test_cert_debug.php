<?php

$certPath = 'storage/certs/certificado.p12';
$password = 'Casabonita25';

if (!file_exists($certPath)) {
    echo "❌ Error: Update path '$certPath' not found.\n";
    // Try absolute path if relative fails, assuming we are in root
    $certPath = __DIR__ . '/storage/certs/certificado.p12';
    if (!file_exists($certPath)) {
         echo "❌ Error: Absolute path '$certPath' not found either.\n";
         exit(1);
    }
}

echo "✅ File found at: $certPath\n";
echo "📦 File size: " . filesize($certPath) . " bytes\n";

$p12Content = file_get_contents($certPath);

$certs = [];
if (openssl_pkcs12_read($p12Content, $certs, $password)) {
    echo "✅ Success! Certificate loaded correctly.\n";
    print_r($certs['cert']);
} else {
    echo "❌ Failed to parse certificate. Possible wrong password or corrupted file.\n";
    while ($msg = openssl_error_string()) {
        echo "   SSL Error: $msg\n";
    }
}
