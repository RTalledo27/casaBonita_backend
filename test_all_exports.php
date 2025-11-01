<?php

echo "========================================\n";
echo "PRUEBA 1: Reporte de Ventas\n";
echo "========================================\n\n";

$url = 'http://localhost:8000/api/v1/reports/export';
$data = [
    'type' => 'sales',
    'format' => 'excel'
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
echo ($httpCode === 200) ? "✅ OK\n" : "❌ ERROR\n";

echo "\n========================================\n";
echo "PRUEBA 2: Proyecciones Estadísticas\n";
echo "========================================\n\n";

$data = [
    'type' => 'projected_statistics',
    'format' => 'excel',
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

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";
echo "Response length: " . strlen($response) . " bytes\n";
echo ($httpCode === 200) ? "✅ OK\n" : "❌ ERROR\n";

if ($httpCode === 200) {
    file_put_contents('test_projected_stats.xlsx', $response);
    echo "Archivo guardado: test_projected_stats.xlsx\n";
}

echo "\n========================================\n";
echo "PRUEBA 3: Cronograma de Cobros\n";
echo "========================================\n\n";

$data = [
    'type' => 'payment_schedule_projection',
    'format' => 'excel',
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

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";
echo "Response length: " . strlen($response) . " bytes\n";
echo ($httpCode === 200) ? "✅ OK\n" : "❌ ERROR\n";

if ($httpCode === 200) {
    file_put_contents('test_payment_schedule.xlsx', $response);
    echo "Archivo guardado: test_payment_schedule.xlsx\n";
}

echo "\n========================================\n";
echo "RESUMEN\n";
echo "========================================\n";
echo "Todos los tipos de reportes funcionan desde:\n";
echo "POST /api/v1/reports/export\n";
echo "\nTipos disponibles:\n";
echo "- sales (ventas)\n";
echo "- projected_statistics (proyecciones estadísticas)\n";
echo "- payment_schedule_projection (cronograma de cobros)\n";
echo "- payment_schedules (cronogramas de pago)\n";
echo "- projections (proyecciones antiguas)\n";
echo "- collections (cobranzas)\n";
echo "- inventory (inventario)\n";
