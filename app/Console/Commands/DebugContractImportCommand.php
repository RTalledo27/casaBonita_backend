<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Modules\Sales\Services\ContractImportService;
use ReflectionClass;
use ReflectionMethod;

class DebugContractImportCommand extends Command
{
    protected $signature = 'debug:contract-import {file}';
    protected $description = 'Debug contract import processing to identify errors and skipped rows';
    
    private $contractImportService;
    
    public function __construct()
    {
        parent::__construct();
        $this->contractImportService = new ContractImportService();
    }
    
    public function handle()
    {
        $filePath = $this->argument('file');
        
        $this->info('=== DEBUG DE PROCESAMIENTO DE FILAS ===');
        $this->newLine();
        
        if (!file_exists($filePath)) {
            $this->error("Archivo no encontrado: {$filePath}");
            return 1;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                $this->error('El archivo está vacío');
                return 1;
            }
            
            $headers = array_shift($rows);
            $this->info('Headers encontrados: ' . implode(', ', $headers));
            $this->newLine();
            
            // Validar estructura
            $validation = $this->contractImportService->validateExcelStructureSimplified($headers);
            if (!$validation['valid']) {
                $this->error('ERROR de validación: ' . $validation['error']);
                return 1;
            }
            
            $this->info('Estructura del archivo válida');
            $this->newLine();
            
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            $emptyCount = 0;
            
            $errorDetails = [];
            $skippedDetails = [];
            
            $this->info('Procesando ' . count($rows) . ' filas...');
            $this->newLine();
            
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                
                $this->line("--- FILA {$rowNumber} ---");
                
                // Verificar si la fila está vacía
                if ($this->isEmptyRow($row)) {
                    $this->line('Fila vacía - OMITIDA');
                    $this->newLine();
                    $emptyCount++;
                    continue;
                }
                
                // Mapear datos
                $mappedData = $this->mapRowData($row, $headers);
                
                $this->line('Datos mapeados:');
                $this->line('  Cliente: ' . ($mappedData['cliente_nombres'] ?? 'N/A'));
                $this->line('  Lote: ' . ($mappedData['lot_number'] ?? 'N/A') . ' Manzana: ' . ($mappedData['lot_manzana'] ?? 'N/A'));
                $this->line('  Tipo Operación: "' . ($mappedData['operation_type'] ?? 'N/A') . '"');
                $this->line('  Estado Contrato: "' . ($mappedData['contract_status'] ?? 'N/A') . '"');
                $this->line('  Asesor: ' . ($mappedData['advisor_name'] ?? 'N/A'));
                
                // Verificar validación shouldCreateContractSimplified
                $shouldCreate = $this->shouldCreateContractSimplified($mappedData);
                $this->line('  ¿Debe crear contrato?: ' . ($shouldCreate ? 'SÍ' : 'NO'));
                
                if (!$shouldCreate) {
                    $this->line('  RAZÓN: Tipo operación no es "venta"/"contrato" Y estado no es "vigente"/"activo"/"firmado"');
                    $skippedCount++;
                    $skippedDetails[] = [
                        'row' => $rowNumber,
                        'reason' => 'No cumple criterios shouldCreateContractSimplified',
                        'operation_type' => $mappedData['operation_type'] ?? '',
                        'contract_status' => $mappedData['contract_status'] ?? ''
                    ];
                    $this->line('  RESULTADO: OMITIDA');
                    $this->newLine();
                    continue;
                }
                
                // Procesar la fila usando reflexión para acceder al método privado
                try {
                    $reflection = new ReflectionClass($this->contractImportService);
                    $processRowMethod = $reflection->getMethod('processRowSimplified');
                    $processRowMethod->setAccessible(true);
                    
                    $result = $processRowMethod->invoke($this->contractImportService, $row, $headers);
                    
                    $this->line('  RESULTADO: ' . strtoupper($result['status']) . ' - ' . $result['message']);
                    
                    if ($result['status'] === 'success') {
                        $successCount++;
                    } elseif ($result['status'] === 'skipped') {
                        $skippedCount++;
                        $skippedDetails[] = [
                            'row' => $rowNumber,
                            'reason' => $result['message'],
                            'data' => $mappedData
                        ];
                    } else {
                        $errorCount++;
                        $errorDetails[] = [
                            'row' => $rowNumber,
                            'error' => $result['message'],
                            'data' => $mappedData
                        ];
                    }
                    
                } catch (\Exception $e) {
                    $this->line('  ERROR: ' . $e->getMessage());
                    $errorCount++;
                    $errorDetails[] = [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                        'data' => $mappedData
                    ];
                }
                
                $this->newLine();
                
                // Limitar a las primeras 20 filas para evitar output muy largo
                if ($index >= 19) {
                    $this->line('... (limitando output a las primeras 20 filas)');
                    $this->newLine();
                    break;
                }
            }
            
            $this->info('=== RESUMEN FINAL ===');
            $this->line("Filas exitosas: {$successCount}");
            $this->line("Filas con error: {$errorCount}");
            $this->line("Filas omitidas: {$skippedCount}");
            $this->line("Filas vacías: {$emptyCount}");
            $this->line("Total procesado: " . ($successCount + $errorCount + $skippedCount + $emptyCount));
            $this->newLine();
            
            if (!empty($errorDetails)) {
                $this->info('=== DETALLES DE ERRORES ===');
                foreach (array_slice($errorDetails, 0, 10) as $error) {
                    $this->line("Fila {$error['row']}: {$error['error']}");
                }
                $this->newLine();
            }
            
            if (!empty($skippedDetails)) {
                $this->info('=== DETALLES DE FILAS OMITIDAS ===');
                foreach (array_slice($skippedDetails, 0, 10) as $skipped) {
                    $this->line("Fila {$skipped['row']}: {$skipped['reason']}");
                    if (isset($skipped['operation_type']) && isset($skipped['contract_status'])) {
                        $this->line("  Tipo: '{$skipped['operation_type']}' Estado: '{$skipped['contract_status']}'");
                    }
                }
                $this->newLine();
            }
            
            // Análisis de tipos de operación y estados
            $this->analyzeOperationTypes($rows, $headers);
            
        } catch (\Exception $e) {
            $this->error('ERROR: ' . $e->getMessage());
            $this->line('Trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
    
    private function isEmptyRow($row)
    {
        foreach ($row as $cell) {
            if (!empty(trim($cell))) {
                return false;
            }
        }
        return true;
    }
    
    private function mapRowData($row, $headers)
    {
        $headerMap = [
            'ASESOR_NOMBRE' => 'advisor_name',
            'ASESOR_CODIGO' => 'advisor_code',
            'ASESOR_EMAIL' => 'advisor_email',
            'CLIENTE_NOMBRE_COMPLETO' => 'cliente_nombres',
            'CLIENTE_TIPO_DOC' => 'cliente_tipo_doc',
            'CLIENTE_NUM_DOC' => 'cliente_num_doc',
            'CLIENTE_TELEFONO_1' => 'cliente_telefono_1',
            'CLIENTE_EMAIL' => 'cliente_email',
            'LOTE_NUMERO' => 'lot_number',
            'LOTE_MANZANA' => 'lot_manzana',
            'FECHA_VENTA' => 'sale_date',
            'TIPO_OPERACION' => 'operation_type',
            'OBSERVACIONES' => 'observaciones',
            'ESTADO_CONTRATO' => 'contract_status'
        ];
        
        $mappedData = [];
        
        foreach ($headers as $index => $header) {
            $normalizedHeader = trim(strtoupper($header));
            if (isset($headerMap[$normalizedHeader])) {
                $mappedData[$headerMap[$normalizedHeader]] = $row[$index] ?? '';
            }
        }
        
        return $mappedData;
    }
    
    private function shouldCreateContractSimplified($data)
    {
        $tipoOperacion = strtolower(trim($data['operation_type'] ?? ''));
        $estadoContrato = strtolower(trim($data['contract_status'] ?? ''));
        
        return in_array($tipoOperacion, ['venta', 'contrato']) || 
               in_array($estadoContrato, ['vigente', 'activo', 'firmado']);
    }
    
    private function analyzeOperationTypes($rows, $headers)
    {
        $this->info('=== ANÁLISIS DE TIPOS DE OPERACIÓN Y ESTADOS ===');
        
        $operationTypes = [];
        $contractStatuses = [];
        $validRows = 0;
        
        foreach ($rows as $row) {
            if ($this->isEmptyRow($row)) continue;
            
            $mappedData = $this->mapRowData($row, $headers);
            $validRows++;
            
            $opType = trim($mappedData['operation_type'] ?? '');
            $contractStatus = trim($mappedData['contract_status'] ?? '');
            
            $operationTypes[$opType] = ($operationTypes[$opType] ?? 0) + 1;
            $contractStatuses[$contractStatus] = ($contractStatuses[$contractStatus] ?? 0) + 1;
        }
        
        $this->line("Total filas no vacías: {$validRows}");
        $this->newLine();
        
        $this->line('Tipos de Operación encontrados:');
        arsort($operationTypes);
        foreach ($operationTypes as $type => $count) {
            $type = $type === '' ? '(VACÍO)' : $type;
            $this->line("  '{$type}': {$count} filas");
        }
        
        $this->newLine();
        $this->line('Estados de Contrato encontrados:');
        arsort($contractStatuses);
        foreach ($contractStatuses as $status => $count) {
            $status = $status === '' ? '(VACÍO)' : $status;
            $this->line("  '{$status}': {$count} filas");
        }
        
        $this->newLine();
    }
}