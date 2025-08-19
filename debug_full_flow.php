<?php

require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\Log;
use Modules\Sales\Services\ContractImportService;
use Modules\Sales\Models\Client;
use Modules\Sales\Models\Lot;
use Modules\Sales\Models\Reservation;
use Modules\Sales\Models\Contract;

// Configurar logging para mostrar en consola
Log::info('=== INICIANDO DEBUG FLUJO COMPLETO ===');

// Datos de prueba exactos del usuario
$testData = [
    ['ALISSON TORRES', '-', '-', 'LUZ AURORA ARMIJOS ROBLEDO', 'DNI', '-', '950285502', '-', '5', 'H', '02/06/2025', 'contrato', 'LEAD KOMMO', 'ACTIVO'],
    ['PAOLA JUDITH CANDELA NEIRA', '-', '-', 'FLOR ANTONELLA ESLAVA CLAVIJO', 'DNI', '-', '989410403', '-', '32', 'E', '04/06/2025', 'CONTRATO', 'LEAD KOMMO', '']
];

$headers = [
    'ASESOR_NOMBRE', 'ASESOR_CODIGO', 'ASESOR_EMAIL', 'CLIENTE_NOMBRE_COMPLETO', 
    'CLIENTE_TIPO_DOC', 'CLIENTE_NUM_DOC', 'CLIENTE_TELEFONO_1', 'CLIENTE_EMAIL',
    'LOTE_NUMERO', 'LOTE_MANZANA', 'FECHA_VENTA', 'TIPO_OPERACION', 'OBSERVACIONES', 'ESTADO_CONTRATO'
];

try {
    $contractImportService = new ContractImportService();
    
    echo "\n=== CONTADORES INICIALES ===\n";
    echo "Clientes: " . Client::count() . "\n";
    echo "Reservaciones: " . Reservation::count() . "\n";
    echo "Contratos: " . Contract::count() . "\n";
    
    foreach ($testData as $index => $row) {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "=== PROCESANDO FILA " . ($index + 1) . " ===\n";
        echo "Cliente: {$row[3]}\n";
        echo "Tipo Operación: '{$row[11]}'\n";
        echo "Estado Contrato: '{$row[13]}'\n";
        echo "Lote: {$row[8]} Manzana: {$row[9]}\n";
        
        // Procesar la fila usando el método real
        $result = $contractImportService->processRowSimplified($row, $headers);
        
        echo "\nResultado del procesamiento:\n";
        echo "Status: " . $result['status'] . "\n";
        echo "Message: " . $result['message'] . "\n";
        
        if ($result['status'] === 'success') {
            echo "Client ID: " . ($result['client_id'] ?? 'N/A') . "\n";
            echo "Lot ID: " . ($result['lot_id'] ?? 'N/A') . "\n";
            echo "Reservation ID: " . ($result['reservation_id'] ?? 'N/A') . "\n";
            echo "Contract ID: " . ($result['contract_id'] ?? 'N/A') . "\n";
            
            if (!empty($result['contract_id'])) {
                echo "✅ CONTRATO CREADO EXITOSAMENTE\n";
            } else {
                echo "❌ NO SE CREÓ CONTRATO\n";
            }
        } else {
            echo "❌ ERROR EN PROCESAMIENTO: " . $result['message'] . "\n";
        }
        
        echo str_repeat('-', 50) . "\n";
    }
    
    echo "\n=== CONTADORES FINALES ===\n";
    echo "Clientes: " . Client::count() . "\n";
    echo "Reservaciones: " . Reservation::count() . "\n";
    echo "Contratos: " . Contract::count() . "\n";
    
    // Mostrar últimos contratos creados
    $latestContracts = Contract::latest()->take(5)->get();
    echo "\n=== ÚLTIMOS CONTRATOS CREADOS ===\n";
    foreach ($latestContracts as $contract) {
        echo "Contract ID: {$contract->contract_id}, Number: {$contract->contract_number}, Status: {$contract->status}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL DEBUG ===\n";