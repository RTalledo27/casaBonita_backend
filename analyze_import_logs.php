<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\app\Services\ContractImportService;
use Modules\Sales\app\Models\ContractImportLog;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

echo "=== ANÁLISIS DE LOGS DE IMPORTACIÓN ===\n";
echo "Analizando los últimos registros de importación...\n\n";

try {
    // Obtener los últimos logs de importación
    $recentLogs = ContractImportLog::orderBy('created_at', 'desc')
        ->take(100)
        ->get();
    
    if ($recentLogs->isEmpty()) {
        echo "No se encontraron logs de importación recientes.\n";
        exit(0);
    }
    
    echo "Logs encontrados: " . $recentLogs->count() . "\n\n";
    
    // Agrupar por status
    $statusCounts = [];
    $errorMessages = [];
    $skipReasons = [];
    
    foreach ($recentLogs as $log) {
        $status = $log->status;
        if (!isset($statusCounts[$status])) {
            $statusCounts[$status] = 0;
        }
        $statusCounts[$status]++;
        
        if ($status === 'error' && $log->error_message) {
            $errorMsg = $log->error_message;
            if (!isset($errorMessages[$errorMsg])) {
                $errorMessages[$errorMsg] = 0;
            }
            $errorMessages[$errorMsg]++;
        }
        
        if ($status === 'skipped' && $log->error_message) {
            $skipMsg = $log->error_message;
            if (!isset($skipReasons[$skipMsg])) {
                $skipReasons[$skipMsg] = 0;
            }
            $skipReasons[$skipMsg]++;
        }
    }
    
    echo "=== RESUMEN POR STATUS ===\n";
    foreach ($statusCounts as $status => $count) {
        echo "$status: $count\n";
    }
    
    echo "\n=== ERRORES MÁS COMUNES ===\n";
    arsort($errorMessages);
    foreach ($errorMessages as $error => $count) {
        echo "[$count veces] $error\n";
    }
    
    echo "\n=== RAZONES DE OMISIÓN MÁS COMUNES ===\n";
    arsort($skipReasons);
    foreach ($skipReasons as $reason => $count) {
        echo "[$count veces] $reason\n";
    }
    
    // Analizar algunos casos específicos
    echo "\n=== ANÁLISIS DETALLADO DE CASOS PROBLEMÁTICOS ===\n";
    
    // Casos de error
    $errorCases = ContractImportLog::where('status', 'error')
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();
    
    echo "\n--- ÚLTIMOS 5 ERRORES ---\n";
    foreach ($errorCases as $case) {
        echo "Fila {$case->row_number}: {$case->error_message}\n";
        if ($case->row_data) {
            $data = json_decode($case->row_data, true);
            echo "  Cliente: " . ($data['cliente_nombres'] ?? 'N/A') . "\n";
            echo "  Lote: " . ($data['lot_number'] ?? 'N/A') . " Manzana: " . ($data['lot_manzana'] ?? 'N/A') . "\n";
            echo "  Tipo Op: '" . ($data['operation_type'] ?? 'N/A') . "' Estado: '" . ($data['contract_status'] ?? 'N/A') . "'\n";
        }
        echo "\n";
    }
    
    // Casos omitidos
    $skippedCases = ContractImportLog::where('status', 'skipped')
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();
    
    echo "--- ÚLTIMOS 5 CASOS OMITIDOS ---\n";
    foreach ($skippedCases as $case) {
        echo "Fila {$case->row_number}: {$case->error_message}\n";
        if ($case->row_data) {
            $data = json_decode($case->row_data, true);
            echo "  Cliente: " . ($data['cliente_nombres'] ?? 'N/A') . "\n";
            echo "  Lote: " . ($data['lot_number'] ?? 'N/A') . " Manzana: " . ($data['lot_manzana'] ?? 'N/A') . "\n";
            echo "  Tipo Op: '" . ($data['operation_type'] ?? 'N/A') . "' Estado: '" . ($data['contract_status'] ?? 'N/A') . "'\n";
        }
        echo "\n";
    }
    
    // Analizar la lógica de shouldCreateContractSimplified
    echo "=== ANÁLISIS DE LÓGICA DE VALIDACIÓN ===\n";
    $contractImportService = new ContractImportService();
    $reflection = new ReflectionClass($contractImportService);
    
    // Obtener el método shouldCreateContractSimplified
    $shouldCreateMethod = $reflection->getMethod('shouldCreateContractSimplified');
    $shouldCreateMethod->setAccessible(true);
    
    echo "Probando diferentes combinaciones de datos:\n\n";
    
    $testCases = [
        ['operation_type' => 'venta', 'contract_status' => ''],
        ['operation_type' => 'contrato', 'contract_status' => ''],
        ['operation_type' => '', 'contract_status' => 'vigente'],
        ['operation_type' => '', 'contract_status' => 'activo'],
        ['operation_type' => '', 'contract_status' => 'firmado'],
        ['operation_type' => 'otro', 'contract_status' => 'otro'],
        ['operation_type' => '', 'contract_status' => ''],
    ];
    
    foreach ($testCases as $testCase) {
        $result = $shouldCreateMethod->invoke($contractImportService, $testCase);
        echo "Tipo: '{$testCase['operation_type']}', Estado: '{$testCase['contract_status']}' => " . ($result ? 'CREAR' : 'OMITIR') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}