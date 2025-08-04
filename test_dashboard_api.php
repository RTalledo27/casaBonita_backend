<?php

// Script para probar el endpoint del dashboard administrativo
echo "=== TEST DASHBOARD API ===\n";

$month = 7; // Julio
$year = 2025;

// URL del endpoint
$url = "http://localhost:8000/api/v1/hr/employees/admin-dashboard?month={$month}&year={$year}";

echo "Probando endpoint: {$url}\n\n";

// Configurar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Ejecutar la petición
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";

if ($error) {
    echo "cURL Error: {$error}\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "Error HTTP: {$httpCode}\n";
    echo "Response: {$response}\n";
    exit(1);
}

// Decodificar la respuesta JSON
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error al decodificar JSON: " . json_last_error_msg() . "\n";
    echo "Raw response: {$response}\n";
    exit(1);
}

echo "=== RESPUESTA DEL API ===\n";

// Mostrar datos de comisiones
if (isset($data['data']['commissions'])) {
    $commissions = $data['data']['commissions'];
    echo "\n=== DATOS DE COMISIONES ===\n";
    echo "Total amount: " . ($commissions['total_amount'] ?? 'N/A') . "\n";
    echo "Count: " . ($commissions['count'] ?? 'N/A') . "\n";
    
    if (isset($commissions['commissions_summary'])) {
        echo "\n=== RESUMEN POR ESTADO ===\n";
        foreach ($commissions['commissions_summary'] as $status => $summary) {
            echo "Estado: {$status}\n";
            if (is_array($summary)) {
                echo "  Cantidad: " . ($summary['count'] ?? 'N/A') . "\n";
                echo "  Monto: $" . number_format($summary['total_amount'] ?? 0, 2) . "\n";
            }
            echo "\n";
        }
    }
}

echo "\n=== RESPUESTA COMPLETA (JSON) ===\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== FIN DEL TEST ===\n";