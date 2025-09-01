<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Simular datos de Excel exactos del usuario
$testData = [
    ['ASESOR_NOMBRE', 'ASESOR_CODIGO', 'ASESOR_EMAIL', 'CLIENTE_NOMBRE_COMPLETO', 'CLIENTE_TIPO_DOC', 'CLIENTE_NUM_DOC', 'CLIENTE_TELEFONO_1', 'CLIENTE_EMAIL', 'LOTE_NUMERO', 'LOTE_MANZANA', 'FECHA_VENTA', 'TIPO_OPERACION', 'OBSERVACIONES', 'ESTADO_CONTRATO'],
    ['ALISSON TORRES', '-', '-', 'LUZ AURORA ARMIJOS ROBLEDO', 'DNI', '-', '950285502', '-', '5', 'H', '02/06/2025', 'contrato', 'LEAD KOMMO', 'ACTIVO'],
    ['PAOLA JUDITH CANDELA NEIRA', '-', '-', 'FLOR ANTONELLA ESLAVA CLAVIJO', 'DNI', '-', '989410403', '-', '32', 'E', '04/06/2025', 'CONTRATO', 'LEAD KOMMO', '']
];

echo "=== PRUEBA DE IMPORTACIÓN DE CONTRATOS ===\n";
echo "Contratos antes: " . Modules\Sales\Models\Contract::count() . "\n";
echo "Clientes antes: " . Modules\Sales\Models\Client::count() . "\n";
echo "Reservaciones antes: " . Modules\Sales\Models\Reservation::count() . "\n\n";

try {
    $service = new Modules\Sales\Services\ContractImportService();
    
    // Procesar cada fila de datos
    $headers = $testData[0];
    for ($i = 1; $i < count($testData); $i++) {
        $row = $testData[$i];
        echo "--- PROCESANDO FILA $i ---\n";
        echo "Cliente: {$row[3]}\n";
        echo "Lote: {$row[8]} Manzana: {$row[9]}\n";
        echo "Tipo Operación: {$row[11]}\n";
        echo "Estado Contrato: {$row[13]}\n\n";
        
        $result = $service->processRowSimplified($row, $headers);
        
        echo "Resultado: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        echo "Contratos después de fila $i: " . Modules\Sales\Models\Contract::count() . "\n";
        echo "Clientes después de fila $i: " . Modules\Sales\Models\Client::count() . "\n";
        echo "Reservaciones después de fila $i: " . Modules\Sales\Models\Reservation::count() . "\n\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== RESUMEN FINAL ===\n";
echo "Contratos finales: " . Modules\Sales\Models\Contract::count() . "\n";
echo "Clientes finales: " . Modules\Sales\Models\Client::count() . "\n";
echo "Reservaciones finales: " . Modules\Sales\Models\Reservation::count() . "\n";