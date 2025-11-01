<?php

echo "🧪 Testing Projected Reports Export\n";
echo "===========================================================\n\n";

// Test the export endpoint
$url = 'http://localhost:8000/v1/reports/projected/export';

$data = [
    'year' => 2025,
    'scenario' => 'realistic',
    'months_ahead' => 12
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
echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
echo "-----------------------------------------------------------\n";

if ($httpCode == 200) {
    echo "✅ Success!\n\n";
    
    $result = json_decode($response, true);
    
    if ($result) {
        echo "📊 Response:\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
        
        if (isset($result['download_url'])) {
            echo "\n📥 Download URL: {$result['download_url']}\n";
        }
    } else {
        echo "Raw response:\n";
        echo $response . "\n";
    }
} else {
    echo "❌ Error!\n";
    echo "Response: {$response}\n";
}

echo "\n===========================================================\n";
echo "✅ Export test complete!\n";
