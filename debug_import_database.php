<?php

require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Script para analizar directamente la base de datos y entender
 * los patrones de error en la importación de contratos
 */

class ImportDatabaseAnalyzer
{
    public function analyzeRecentImports()
    {
        echo "=== ANÁLISIS DE IMPORTACIONES RECIENTES ===\n\n";
        
        try {
            // Verificar si existe la tabla contract_import_logs
            $tableExists = DB::select("SHOW TABLES LIKE 'contract_import_logs'");
            
            if (empty($tableExists)) {
                echo "La tabla 'contract_import_logs' no existe.\n";
                echo "Analizando directamente las tablas de contratos y reservaciones...\n\n";
                $this->analyzeContractsAndReservations();
                return;
            }
            
            // Obtener estadísticas de los últimos imports
            $recentLogs = DB::table('contract_import_logs')
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
                
            if ($recentLogs->isEmpty()) {
                echo "No hay registros de importación recientes.\n";
                return;
            }
            
            $stats = [
                'total' => $recentLogs->count(),
                'success' => 0,
                'error' => 0,
                'skipped' => 0,
                'error_messages' => [],
                'skip_reasons' => []
            ];
            
            foreach ($recentLogs as $log) {
                switch ($log->status) {
                    case 'success':
                        $stats['success']++;
                        break;
                    case 'error':
                        $stats['error']++;
                        if (!empty($log->error_message)) {
                            $errorKey = substr($log->error_message, 0, 100); // Primeros 100 chars
                            $stats['error_messages'][$errorKey] = ($stats['error_messages'][$errorKey] ?? 0) + 1;
                        }
                        break;
                    case 'skipped':
                        $stats['skipped']++;
                        if (!empty($log->skip_reason)) {
                            $stats['skip_reasons'][$log->skip_reason] = ($stats['skip_reasons'][$log->skip_reason] ?? 0) + 1;
                        }
                        break;
                }
            }
            
            $this->printImportStats($stats);
            
            // Mostrar ejemplos de errores y skips
            $this->showErrorExamples($recentLogs);
            
        } catch (Exception $e) {
            echo "Error analizando la base de datos: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
    
    private function analyzeContractsAndReservations()
    {
        try {
            // Contar contratos recientes
            $recentContracts = DB::table('contracts')
                ->where('created_at', '>=', now()->subDays(1))
                ->count();
                
            echo "Contratos creados en las últimas 24 horas: $recentContracts\n";
            
            // Contar reservaciones recientes
            $recentReservations = DB::table('reservations')
                ->where('created_at', '>=', now()->subDays(1))
                ->count();
                
            echo "Reservaciones creadas en las últimas 24 horas: $recentReservations\n";
            
            // Verificar lotes disponibles
            $availableLots = DB::table('lots')
                ->where('status', 'disponible')
                ->count();
                
            echo "Lotes disponibles: $availableLots\n";
            
            // Verificar proyectos activos
            $activeProjects = DB::table('projects')
                ->where('status', 'activo')
                ->count();
                
            echo "Proyectos activos: $activeProjects\n";
            
            // Verificar asesores disponibles
            $advisors = DB::table('employees')
                ->where('employee_type', 'asesor_inmobiliario')
                ->count();
                
            echo "Asesores inmobiliarios registrados: $advisors\n\n";
            
        } catch (Exception $e) {
            echo "Error analizando contratos y reservaciones: " . $e->getMessage() . "\n";
        }
    }
    
    private function printImportStats($stats)
    {
        echo "Total de registros analizados: {$stats['total']}\n";
        echo "Exitosos: {$stats['success']}\n";
        echo "Errores: {$stats['error']}\n";
        echo "Omitidos: {$stats['skipped']}\n\n";
        
        if (!empty($stats['error_messages'])) {
            echo "=== MENSAJES DE ERROR MÁS COMUNES ===\n";
            arsort($stats['error_messages']);
            foreach (array_slice($stats['error_messages'], 0, 5) as $message => $count) {
                echo "[$count veces] $message\n";
            }
            echo "\n";
        }
        
        if (!empty($stats['skip_reasons'])) {
            echo "=== RAZONES DE OMISIÓN MÁS COMUNES ===\n";
            arsort($stats['skip_reasons']);
            foreach ($stats['skip_reasons'] as $reason => $count) {
                echo "[$count veces] $reason\n";
            }
            echo "\n";
        }
    }
    
    private function showErrorExamples($logs)
    {
        echo "=== EJEMPLOS DE ERRORES RECIENTES ===\n";
        
        $errorExamples = $logs->where('status', 'error')->take(5);
        foreach ($errorExamples as $log) {
            echo "Fila {$log->row_number}: {$log->error_message}\n";
            if (!empty($log->row_data)) {
                $rowData = json_decode($log->row_data, true);
                if ($rowData) {
                    echo "  Datos: " . json_encode($rowData, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
            echo "\n";
        }
        
        echo "=== EJEMPLOS DE FILAS OMITIDAS ===\n";
        
        $skipExamples = $logs->where('status', 'skipped')->take(5);
        foreach ($skipExamples as $log) {
            echo "Fila {$log->row_number}: {$log->skip_reason}\n";
            if (!empty($log->row_data)) {
                $rowData = json_decode($log->row_data, true);
                if ($rowData) {
                    echo "  Datos: " . json_encode($rowData, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
            echo "\n";
        }
    }
    
    public function testValidationLogic()
    {
        echo "\n=== PRUEBA DE LÓGICA DE VALIDACIÓN ===\n";
        
        $testCases = [
            ['operation_type' => 'venta', 'contract_status' => ''],
            ['operation_type' => 'contrato', 'contract_status' => ''],
            ['operation_type' => '', 'contract_status' => 'vigente'],
            ['operation_type' => '', 'contract_status' => 'activo'],
            ['operation_type' => '', 'contract_status' => 'firmado'],
            ['operation_type' => 'reserva', 'contract_status' => ''],
            ['operation_type' => '', 'contract_status' => 'pendiente'],
            ['operation_type' => '', 'contract_status' => ''],
        ];
        
        foreach ($testCases as $case) {
            $shouldCreate = $this->shouldCreateContractSimplified($case);
            echo "operation_type: '{$case['operation_type']}', contract_status: '{$case['contract_status']}' -> " . 
                 ($shouldCreate ? 'CREAR' : 'NO CREAR') . "\n";
        }
    }
    
    private function shouldCreateContractSimplified($data)
    {
        $tipoOperacion = strtolower($data['operation_type'] ?? '');
        $estadoContrato = strtolower($data['contract_status'] ?? '');
        
        return in_array($tipoOperacion, ['venta', 'contrato']) || 
               in_array($estadoContrato, ['vigente', 'activo', 'firmado']);
    }
}

// Ejecutar el análisis
$analyzer = new ImportDatabaseAnalyzer();
$analyzer->analyzeRecentImports();
$analyzer->testValidationLogic();