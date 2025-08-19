<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use Modules\Sales\app\Services\ContractImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RowProcessingDebugger
{
    private $contractImportService;
    
    public function __construct()
    {
        $this->contractImportService = new ContractImportService();
    }
    
    public function debugProcessing($filePath)
    {
        echo "=== DEBUG DE PROCESAMIENTO DE FILAS ===\n\n";
        
        if (!file_exists($filePath)) {
            echo "ERROR: Archivo no encontrado: {$filePath}\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                echo "ERROR: El archivo está vacío\n";
                return;
            }
            
            $headers = array_shift($rows);
            echo "Headers encontrados: " . implode(', ', $headers) . "\n\n";
            
            // Validar estructura
            $validation = $this->contractImportService->validateExcelStructureSimplified($headers);
            if (!$validation['valid']) {
                echo "ERROR de validación: " . $validation['error'] . "\n";
                return;
            }
            
            echo "Estructura del archivo válida\n\n";
            
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            $emptyCount = 0;
            
            $errorDetails = [];
            $skippedDetails = [];
            
            echo "Procesando " . count($rows) . " filas...\n\n";
            
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                
                echo "--- FILA {$rowNumber} ---\n";
                
                // Verificar si la fila está vacía
                if ($this->isEmptyRow($row)) {
                    echo "Fila vacía - OMITIDA\n\n";
                    $emptyCount++;
                    continue;
                }
                
                // Mapear datos
                $mappedData = $this->mapRowData($row, $headers);
                
                echo "Datos mapeados:\n";
                echo "  Cliente: " . ($mappedData['cliente_nombres'] ?? 'N/A') . "\n";
                echo "  Lote: " . ($mappedData['lot_number'] ?? 'N/A') . " Manzana: " . ($mappedData['lot_manzana'] ?? 'N/A') . "\n";
                echo "  Tipo Operación: '" . ($mappedData['operation_type'] ?? 'N/A') . "'\n";
                echo "  Estado Contrato: '" . ($mappedData['contract_status'] ?? 'N/A') . "'\n";
                echo "  Asesor: " . ($mappedData['advisor_name'] ?? 'N/A') . "\n";
                
                // Verificar validación shouldCreateContractSimplified
                $shouldCreate = $this->shouldCreateContractSimplified($mappedData);
                echo "  ¿Debe crear contrato?: " . ($shouldCreate ? 'SÍ' : 'NO') . "\n";
                
                if (!$shouldCreate) {
                    echo "  RAZÓN: Tipo operación no es 'venta'/'contrato' Y estado no es 'vigente'/'activo'/'firmado'\n";
                    $skippedCount++;
                    $skippedDetails[] = [
                        'row' => $rowNumber,
                        'reason' => 'No cumple criterios shouldCreateContractSimplified',
                        'operation_type' => $mappedData['operation_type'] ?? '',
                        'contract_status' => $mappedData['contract_status'] ?? ''
                    ];
                    echo "  RESULTADO: OMITIDA\n\n";
                    continue;
                }
                
                // Procesar la fila usando reflexión para acceder al método privado
                try {
                    $reflection = new ReflectionClass($this->contractImportService);
                    $processRowMethod = $reflection->getMethod('processRowSimplified');
                    $processRowMethod->setAccessible(true);
                    
                    $result = $processRowMethod->invoke($this->contractImportService, $row, $headers);
                    
                    echo "  RESULTADO: " . strtoupper($result['status']) . " - " . $result['message'] . "\n";
                    
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
                    
                } catch (Exception $e) {
                    echo "  ERROR: " . $e->getMessage() . "\n";
                    $errorCount++;
                    $errorDetails[] = [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                        'data' => $mappedData
                    ];
                }
                
                echo "\n";
                
                // Limitar a las primeras 20 filas para evitar output muy largo
                if ($index >= 19) {
                    echo "... (limitando output a las primeras 20 filas)\n\n";
                    break;
                }
            }
            
            echo "=== RESUMEN FINAL ===\n";
            echo "Filas exitosas: {$successCount}\n";
            echo "Filas con error: {$errorCount}\n";
            echo "Filas omitidas: {$skippedCount}\n";
            echo "Filas vacías: {$emptyCount}\n";
            echo "Total procesado: " . ($successCount + $errorCount + $skippedCount + $emptyCount) . "\n\n";
            
            if (!empty($errorDetails)) {
                echo "=== DETALLES DE ERRORES ===\n";
                foreach (array_slice($errorDetails, 0, 10) as $error) {
                    echo "Fila {$error['row']}: {$error['error']}\n";
                }
                echo "\n";
            }
            
            if (!empty($skippedDetails)) {
                echo "=== DETALLES DE FILAS OMITIDAS ===\n";
                foreach (array_slice($skippedDetails, 0, 10) as $skipped) {
                    echo "Fila {$skipped['row']}: {$skipped['reason']}\n";
                    if (isset($skipped['operation_type']) && isset($skipped['contract_status'])) {
                        echo "  Tipo: '{$skipped['operation_type']}' Estado: '{$skipped['contract_status']}'\n";
                    }
                }
                echo "\n";
            }
            
            // Análisis de tipos de operación y estados
            $this->analyzeOperationTypes($rows, $headers);
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
        }
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
        echo "=== ANÁLISIS DE TIPOS DE OPERACIÓN Y ESTADOS ===\n";
        
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
        
        echo "Total filas no vacías: {$validRows}\n\n";
        
        echo "Tipos de Operación encontrados:\n";
        arsort($operationTypes);
        foreach ($operationTypes as $type => $count) {
            $type = $type === '' ? '(VACÍO)' : $type;
            echo "  '{$type}': {$count} filas\n";
        }
        
        echo "\nEstados de Contrato encontrados:\n";
        arsort($contractStatuses);
        foreach ($contractStatuses as $status => $count) {
            $status = $status === '' ? '(VACÍO)' : $status;
            echo "  '{$status}': {$count} filas\n";
        }
        
        echo "\n";
    }
}

// Ejecutar el debug
if ($argc < 2) {
    echo "Uso: php debug_row_processing.php <ruta_archivo_excel>\n";
    echo "Ejemplo: php debug_row_processing.php test_contracts_simplified.xlsx\n";
    exit(1);
}

$filePath = $argv[1];
$debugger = new RowProcessingDebugger();
$debugger->debugProcessing($filePath);