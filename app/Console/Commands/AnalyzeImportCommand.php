<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyzeImportCommand extends Command
{
    protected $signature = 'import:analyze';
    protected $description = 'Analiza los registros de importación de contratos para identificar errores';

    public function handle()
    {
        $this->info('=== ANÁLISIS DE IMPORTACIONES DE CONTRATOS ===');
        $this->newLine();
        
        try {
            // Verificar si existe la tabla contract_import_logs
            $tableExists = DB::select("SHOW TABLES LIKE 'contract_import_logs'");
            
            if (empty($tableExists)) {
                $this->warn('La tabla contract_import_logs no existe.');
                $this->info('Analizando directamente las tablas de contratos y reservaciones...');
                $this->analyzeContractsAndReservations();
                return;
            }
            
            // Obtener estadísticas de los últimos imports
            $recentLogs = DB::table('contract_import_logs')
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
                
            if ($recentLogs->isEmpty()) {
                $this->warn('No hay registros de importación recientes.');
                return;
            }
            
            $this->analyzeImportLogs($recentLogs);
            $this->showErrorExamples($recentLogs);
            $this->testValidationLogic();
            
        } catch (\Exception $e) {
            $this->error('Error analizando la base de datos: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
    
    private function analyzeContractsAndReservations()
    {
        try {
            // Contar contratos recientes
            $recentContracts = DB::table('contracts')
                ->where('created_at', '>=', now()->subDays(1))
                ->count();
                
            $this->info("Contratos creados en las últimas 24 horas: $recentContracts");
            
            // Contar reservaciones recientes
            $recentReservations = DB::table('reservations')
                ->where('created_at', '>=', now()->subDays(1))
                ->count();
                
            $this->info("Reservaciones creadas en las últimas 24 horas: $recentReservations");
            
            // Verificar lotes disponibles
            $availableLots = DB::table('lots')
                ->where('status', 'disponible')
                ->count();
                
            $this->info("Lotes disponibles: $availableLots");
            
            // Verificar proyectos activos
            $activeProjects = DB::table('projects')
                ->where('status', 'activo')
                ->count();
                
            $this->info("Proyectos activos: $activeProjects");
            
            // Verificar asesores disponibles
            $advisors = DB::table('employees')
                ->where('employee_type', 'asesor_inmobiliario')
                ->count();
                
            $this->info("Asesores inmobiliarios registrados: $advisors");
            $this->newLine();
            
        } catch (\Exception $e) {
            $this->error('Error analizando contratos y reservaciones: ' . $e->getMessage());
        }
    }
    
    private function analyzeImportLogs($logs)
    {
        $stats = [
            'total' => $logs->count(),
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
            'error_messages' => [],
            'skip_reasons' => []
        ];
        
        foreach ($logs as $log) {
            switch ($log->status) {
                case 'success':
                    $stats['success']++;
                    break;
                case 'error':
                    $stats['error']++;
                    if (!empty($log->error_message)) {
                        $errorKey = substr($log->error_message, 0, 100);
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
        
        $this->info("Total de registros analizados: {$stats['total']}");
        $this->info("Exitosos: {$stats['success']}");
        $this->error("Errores: {$stats['error']}");
        $this->warn("Omitidos: {$stats['skipped']}");
        $this->newLine();
        
        if (!empty($stats['error_messages'])) {
            $this->info('=== MENSAJES DE ERROR MÁS COMUNES ===');
            arsort($stats['error_messages']);
            foreach (array_slice($stats['error_messages'], 0, 5) as $message => $count) {
                $this->line("[$count veces] $message");
            }
            $this->newLine();
        }
        
        if (!empty($stats['skip_reasons'])) {
            $this->info('=== RAZONES DE OMISIÓN MÁS COMUNES ===');
            arsort($stats['skip_reasons']);
            foreach ($stats['skip_reasons'] as $reason => $count) {
                $this->line("[$count veces] $reason");
            }
            $this->newLine();
        }
    }
    
    private function showErrorExamples($logs)
    {
        $this->info('=== EJEMPLOS DE ERRORES RECIENTES ===');
        
        $errorExamples = $logs->where('status', 'error')->take(5);
        foreach ($errorExamples as $log) {
            $this->line("Fila {$log->row_number}: {$log->error_message}");
            if (!empty($log->row_data)) {
                $rowData = json_decode($log->row_data, true);
                if ($rowData) {
                    $this->line("  Datos: " . json_encode($rowData, JSON_UNESCAPED_UNICODE));
                }
            }
            $this->newLine();
        }
        
        $this->info('=== EJEMPLOS DE FILAS OMITIDAS ===');
        
        $skipExamples = $logs->where('status', 'skipped')->take(5);
        foreach ($skipExamples as $log) {
            $this->line("Fila {$log->row_number}: {$log->skip_reason}");
            if (!empty($log->row_data)) {
                $rowData = json_decode($log->row_data, true);
                if ($rowData) {
                    $this->line("  Datos: " . json_encode($rowData, JSON_UNESCAPED_UNICODE));
                }
            }
            $this->newLine();
        }
    }
    
    private function testValidationLogic()
    {
        $this->info('=== PRUEBA DE LÓGICA DE VALIDACIÓN ===');
        
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
            $result = $shouldCreate ? 'CREAR' : 'NO CREAR';
            $this->line("operation_type: '{$case['operation_type']}', contract_status: '{$case['contract_status']}' -> $result");
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