<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\Services\ContractImportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

echo "=== DEBUG CONTRACT CREATION ===\n";
echo "Probando con datos exactos del usuario\n\n";

// Datos exactos del usuario
$testData = [
    [
        'ASESOR_NOMBRE' => 'ALISSON TORRES',
        'ASESOR_CODIGO' => '-',
        'ASESOR_EMAIL' => '-',
        'CLIENTE_NOMBRE_COMPLETO' => 'LUZ AURORA ARMIJOS ROBLEDO',
        'CLIENTE_TIPO_DOC' => 'DNI',
        'CLIENTE_NUM_DOC' => '-',
        'CLIENTE_TELEFONO_1' => '950285502',
        'CLIENTE_EMAIL' => '-',
        'LOTE_NUMERO' => '5',
        'LOTE_MANZANA' => 'H',
        'FECHA_VENTA' => '02/06/2025',
        'TIPO_OPERACION' => 'contrato',
        'OBSERVACIONES' => 'LEAD KOMMO',
        'ESTADO_CONTRATO' => 'ACTIVO'
    ],
    [
        'ASESOR_NOMBRE' => 'PAOLA JUDITH CANDELA NEIRA',
        'ASESOR_CODIGO' => '-',
        'ASESOR_EMAIL' => '-',
        'CLIENTE_NOMBRE_COMPLETO' => 'FLOR ANTONELLA ESLAVA CLAVIJO',
        'CLIENTE_TIPO_DOC' => 'DNI',
        'CLIENTE_NUM_DOC' => '-',
        'CLIENTE_TELEFONO_1' => '989410403',
        'CLIENTE_EMAIL' => '-',
        'LOTE_NUMERO' => '32',
        'LOTE_MANZANA' => 'E',
        'FECHA_VENTA' => '04/06/2025',
        'TIPO_OPERACION' => 'CONTRATO',
        'OBSERVACIONES' => 'LEAD KOMMO',
        'ESTADO_CONTRATO' => ''
    ]
];

try {
    $contractImportService = new ContractImportService();
    
    echo "Iniciando prueba de importación...\n";
    echo "Datos de prueba preparados: " . count($testData) . " filas\n\n";
    
    foreach ($testData as $index => $row) {
        echo "=== PROCESANDO FILA " . ($index + 1) . " ===\n";
        echo "Cliente: " . $row['CLIENTE_NOMBRE_COMPLETO'] . "\n";
        echo "Tipo Operación: '" . $row['TIPO_OPERACION'] . "'\n";
        echo "Estado Contrato: '" . $row['ESTADO_CONTRATO'] . "'\n";
        echo "Lote: " . $row['LOTE_NUMERO'] . " Manzana: " . $row['LOTE_MANZANA'] . "\n\n";
        
        // Mapear los datos usando el método interno
        $reflection = new ReflectionClass($contractImportService);
        $mapMethod = $reflection->getMethod('mapSimplifiedHeaders');
        $mapMethod->setAccessible(true);
        
        echo "1. Mapeando headers...\n";
        $mappedHeaders = $mapMethod->invoke($contractImportService, array_keys($row));
        echo "Headers mapeados: " . json_encode($mappedHeaders, JSON_PRETTY_PRINT) . "\n\n";
        
        // Mapear los datos de la fila
        $mapRowMethod = $reflection->getMethod('mapRowDataSimplified');
        $mapRowMethod->setAccessible(true);
        
        echo "2. Mapeando datos de fila...\n";
        // Convertir el row asociativo a array indexado
        $rowValues = array_values($row);
        $headers = array_keys($row);
        $mappedData = $mapRowMethod->invoke($contractImportService, $rowValues, $headers);
        echo "Datos mapeados: " . json_encode($mappedData, JSON_PRETTY_PRINT) . "\n\n";
        
        // Verificar si debe crear contrato
        $shouldCreateMethod = $reflection->getMethod('shouldCreateContractSimplified');
        $shouldCreateMethod->setAccessible(true);
        
        echo "3. Verificando si debe crear contrato...\n";
        echo "operation_type: '" . ($mappedData['operation_type'] ?? 'NO_DEFINIDO') . "'\n";
        echo "contract_status: '" . ($mappedData['contract_status'] ?? 'NO_DEFINIDO') . "'\n";
        
        $shouldCreate = $shouldCreateMethod->invoke(
            $contractImportService, 
            $mappedData
        );
        
        echo "¿Debe crear contrato? " . ($shouldCreate ? 'SÍ' : 'NO') . "\n\n";
        
        if (!$shouldCreate) {
            echo "❌ PROBLEMA: No se creará contrato para esta fila\n";
            echo "Valores recibidos en shouldCreateContractSimplified:\n";
            echo "- operation_type: '" . ($mappedData['operation_type'] ?? 'VACÍO') . "'\n";
            echo "- contract_status: '" . ($mappedData['contract_status'] ?? 'VACÍO') . "'\n";
            
            // Verificar condiciones específicas
            $opType = strtolower($mappedData['operation_type'] ?? '');
            $contractStatus = strtolower($mappedData['contract_status'] ?? '');
            
            echo "Valores en minúsculas para comparación:\n";
            echo "- operation_type: '$opType'\n";
            echo "- contract_status: '$contractStatus'\n";
            
            $validOpTypes = ['venta', 'contrato'];
            $validStatuses = ['vigente', 'activo', 'firmado'];
            
            echo "¿operation_type válido? " . (in_array($opType, $validOpTypes) ? 'SÍ' : 'NO') . "\n";
            echo "¿contract_status válido? " . (in_array($contractStatus, $validStatuses) ? 'SÍ' : 'NO') . "\n";
        } else {
            echo "✅ Se creará contrato para esta fila\n";
        }
        
        echo "\n" . str_repeat('-', 50) . "\n\n";
    }
    
    echo "=== RESUMEN DE DIAGNÓSTICO ===\n";
    echo "Si alguna fila muestra 'NO se creará contrato', revisar:\n";
    echo "1. Mapeo de campos en mapSimplifiedHeaders\n";
    echo "2. Lógica de shouldCreateContractSimplified\n";
    echo "3. Valores exactos que llegan vs valores esperados\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}