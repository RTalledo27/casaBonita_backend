<?php

// Test the sales export endpoint
$url = 'http://localhost:8000/api/v1/reports/export';

$data = [
    'type' => 'sales',
    'format' => 'excel',
    'filters' => []
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";
echo "Response length: " . strlen($response) . " bytes\n";

if ($httpCode === 200) {
    // Check if it's a file or JSON
    if (strpos($contentType, 'application/vnd.openxmlformats') !== false) {
        echo "✅ File downloaded successfully!\n";
        file_put_contents('test_download.xlsx', $response);
        echo "File saved as test_download.xlsx\n";
    } else {
        echo "Response (first 500 chars):\n";
        echo substr($response, 0, 500) . "\n";
    }
} else {
    echo "❌ Error response:\n";
    echo $response . "\n";
}
