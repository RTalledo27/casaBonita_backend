<?php

// Test the projected reports export endpoint
$url = 'http://localhost:8000/api/v1/reports/projected/export';

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

echo "Enviando request a: $url\n";
echo "Datos: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";
echo "Response length: " . strlen($response) . " bytes\n\n";

if ($httpCode === 200) {
    // Check if it's a file or JSON
    if (strpos($contentType, 'application/vnd.openxmlformats') !== false ||
        strpos($contentType, 'application/octet-stream') !== false) {
        echo "✅ Archivo Excel descargado correctamente!\n";
        $filename = 'test_projected_report_' . date('YmdHis') . '.xlsx';
        file_put_contents($filename, $response);
        echo "Archivo guardado como: $filename\n";
        echo "Tamaño del archivo: " . number_format(strlen($response)) . " bytes\n";
        
        // Try to verify it's a valid Excel file
        $header = substr($response, 0, 4);
        if ($header === 'PK' . chr(3) . chr(4)) {
            echo "✅ Archivo tiene firma válida de ZIP/Excel\n";
        } else {
            echo "⚠️  Advertencia: El archivo no tiene firma de ZIP/Excel válida\n";
        }
    } else {
        echo "Response (first 1000 chars):\n";
        echo substr($response, 0, 1000) . "\n";
    }
} else {
    echo "❌ Error response:\n";
    echo $response . "\n";
}
