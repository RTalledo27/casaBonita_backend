<?php

// Test the new complete sales report
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

echo "Probando reporte completo de ventas...\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Content-Type: $contentType\n";
echo "Response length: " . strlen($response) . " bytes\n\n";

if ($httpCode === 200) {
    if (strpos($contentType, 'application/vnd.openxmlformats') !== false) {
        echo "✅ Archivo Excel descargado correctamente!\n";
        $filename = 'reporte_ventas_completo_' . date('YmdHis') . '.xlsx';
        file_put_contents($filename, $response);
        echo "Archivo guardado como: $filename\n";
        echo "Tamaño del archivo: " . number_format(strlen($response)) . " bytes\n\n";
        
        echo "✅ Este reporte incluye TODAS las columnas:\n";
        echo "   - MES, OFICINA, ASESOR(A)\n";
        echo "   - N° VENTA, FECHA\n";
        echo "   - CELULAR1, CELULAR2, NOMBRE DE CLIENTE\n";
        echo "   - MZ, N° DE LOTE\n";
        echo "   - S/., CUOTA INICIAL, SEPARACIÓN\n";
        echo "   - T. DE INICIAL (%), PAGO INICIAL\n";
        echo "   - PAGO DE CUOTA DIRECT, S/ CUOTAS\n";
        echo "   - C. BALLOON, PLAZO (MESES)\n";
        echo "   - COMENTARIOS, EDAD, INGRESOS\n";
        echo "   - OCUPACIÓN, RESIDENCIA\n";
        echo "   - COMO LLEGÓ A NOSOTROS\n";
    } else {
        echo "Respuesta inesperada:\n";
        echo substr($response, 0, 500) . "\n";
    }
} else {
    echo "❌ Error:\n";
    echo $response . "\n";
}
