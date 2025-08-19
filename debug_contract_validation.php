<?php

require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\Log;
use Modules\Sales\app\Services\ContractImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Script para debuggear específicamente la validación de contratos
 * y entender por qué se omiten tantas filas
 */

class ContractValidationDebugger
{
    private $contractImportService;
    
    public function __construct()
    {
        $this->contractImportService = new ContractImportService();
    }
    
    public function analyzeExcelFile($filePath)
    {
        if (!file_exists($filePath)) {
            echo "Archivo no encontrado: $filePath\n";
            return;
        }
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Obtener headers (primera fila)
            $headers = array_shift($rows);
            echo "Headers encontrados: " . implode(', ', $headers) . "\n\n";
            
            $stats = [
                'total_rows' => count($rows),
                'empty_rows' => 0,
                'should_create_true' => 0,
                'should_create_false' => 0,
                'operation_type_analysis' => [],
                'contract_status_analysis' => [],
                'detailed_analysis' => []
            ];
            
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque empezamos desde 0 y saltamos header
                
                // Mapear datos usando los headers
                $data = array_combine($headers, $row);
                
                // Limpiar datos
                $cleanData = [];
                foreach ($data as $key => $value) {
                    $cleanData[trim($key)] = is_string($value) ? trim($value) : $value;
                }
                
                // Mapear a campos esperados
                $mappedData = $this->mapRowData($cleanData);
                
                // Verificar si es fila vacía
                if ($this->isEmptyRow($mappedData)) {
                    $stats['empty_rows']++;
                    continue;
                }
                
                // Analizar shouldCreateContractSimplified
                $shouldCreate = $this->testShouldCreateContract($mappedData);
                
                if ($shouldCreate) {
                    $stats['should_create_true']++;
                } else {
                    $stats['should_create_false']++;
                }
                
                // Análisis detallado de operation_type
                $operationType = strtolower($mappedData['operation_type'] ?? '');
                if (!isset($stats['operation_type_analysis'][$operationType])) {
                    $stats['operation_type_analysis'][$operationType] = 0;
                }
                $stats['operation_type_analysis'][$operationType]++;
                
                // Análisis detallado de contract_status
                $contractStatus = strtolower($mappedData['contract_status'] ?? '');
                if (!isset($stats['contract_status_analysis'][$contractStatus])) {
                    $stats['contract_status_analysis'][$contractStatus] = 0;
                }
                $stats['contract_status_analysis'][$contractStatus]++;
                
                // Guardar ejemplos detallados de los primeros 10 casos
                if (count($stats['detailed_analysis']) < 10) {
                    $stats['detailed_analysis'][] = [
                        'row_number' => $rowNumber,
                        'operation_type_original' => $mappedData['operation_type'] ?? 'NO_DEFINIDO',
                        'operation_type_lowercase' => $operationType,
                        'contract_status_original' => $mappedData['contract_status'] ?? 'NO_DEFINIDO', 
                        'contract_status_lowercase' => $contractStatus,
                        'should_create' => $shouldCreate,
                        'reason' => $this->getValidationReason($mappedData)
                    ];
                }
            }
            
            $this->printAnalysisResults($stats);
            
        } catch (Exception $e) {
            echo "Error procesando archivo: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
    
    private function mapRowData($data)
    {
        // Mapeo simplificado basado en el template de 14 campos
        return [
            'operation_type' => $data['TIPO_OPERACION'] ?? '',
            'contract_status' => $data['ESTADO_CONTRATO'] ?? '',
            'client_name' => $data['NOMBRE_CLIENTE'] ?? '',
            'client_phone' => $data['TELEFONO_CLIENTE'] ?? '',
            'client_email' => $data['EMAIL_CLIENTE'] ?? '',
            'lot_number' => $data['NUMERO_LOTE'] ?? '',
            'manzana' => $data['MANZANA'] ?? '',
            'project_name' => $data['NOMBRE_PROYECTO'] ?? '',
            'sale_date' => $data['FECHA_VENTA'] ?? '',
            'advisor_name' => $data['NOMBRE_ASESOR'] ?? '',
            'advisor_code' => $data['CODIGO_ASESOR'] ?? '',
            'advisor_email' => $data['EMAIL_ASESOR'] ?? '',
            'notes' => $data['OBSERVACIONES'] ?? '',
            'contract_date' => $data['FECHA_CONTRATO'] ?? ''
        ];
    }
    
    private function isEmptyRow($data)
    {
        $requiredFields = ['client_name', 'lot_number', 'project_name'];
        
        foreach ($requiredFields as $field) {
            if (!empty($data[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    private function testShouldCreateContract($data)
    {
        $tipoOperacion = strtolower($data['operation_type'] ?? '');
        $estadoContrato = strtolower($data['contract_status'] ?? '');
        
        return in_array($tipoOperacion, ['venta', 'contrato']) || 
               in_array($estadoContrato, ['vigente', 'activo', 'firmado']);
    }
    
    private function getValidationReason($data)
    {
        $tipoOperacion = strtolower($data['operation_type'] ?? '');
        $estadoContrato = strtolower($data['contract_status'] ?? '');
        
        $reasons = [];
        
        if (in_array($tipoOperacion, ['venta', 'contrato'])) {
            $reasons[] = "operation_type válido: $tipoOperacion";
        } else {
            $reasons[] = "operation_type inválido: $tipoOperacion";
        }
        
        if (in_array($estadoContrato, ['vigente', 'activo', 'firmado'])) {
            $reasons[] = "contract_status válido: $estadoContrato";
        } else {
            $reasons[] = "contract_status inválido: $estadoContrato";
        }
        
        return implode(' | ', $reasons);
    }
    
    private function printAnalysisResults($stats)
    {
        echo "\n=== ANÁLISIS DE VALIDACIÓN DE CONTRATOS ===\n";
        echo "Total de filas procesadas: {$stats['total_rows']}\n";
        echo "Filas vacías: {$stats['empty_rows']}\n";
        echo "Filas que DEBERÍAN crear contrato: {$stats['should_create_true']}\n";
        echo "Filas que NO deberían crear contrato: {$stats['should_create_false']}\n";
        
        echo "\n=== ANÁLISIS DE OPERATION_TYPE ===\n";
        foreach ($stats['operation_type_analysis'] as $type => $count) {
            $type = empty($type) ? '[VACÍO]' : $type;
            echo "$type: $count filas\n";
        }
        
        echo "\n=== ANÁLISIS DE CONTRACT_STATUS ===\n";
        foreach ($stats['contract_status_analysis'] as $status => $count) {
            $status = empty($status) ? '[VACÍO]' : $status;
            echo "$status: $count filas\n";
        }
        
        echo "\n=== EJEMPLOS DETALLADOS (primeras 10 filas) ===\n";
        foreach ($stats['detailed_analysis'] as $example) {
            echo "Fila {$example['row_number']}:\n";
            echo "  - Operation Type: '{$example['operation_type_original']}' -> '{$example['operation_type_lowercase']}'\n";
            echo "  - Contract Status: '{$example['contract_status_original']}' -> '{$example['contract_status_lowercase']}'\n";
            echo "  - Should Create: " . ($example['should_create'] ? 'SÍ' : 'NO') . "\n";
            echo "  - Razón: {$example['reason']}\n";
            echo "\n";
        }
    }
}

// Ejecutar el análisis
if ($argc < 2) {
    echo "Uso: php debug_contract_validation.php <ruta_archivo_excel>\n";
    echo "Ejemplo: php debug_contract_validation.php storage/app/test_contracts_simplified.xlsx\n";
    exit(1);
}

$filePath = $argv[1];
$debugger = new ContractValidationDebugger();
$debugger->analyzeExcelFile($filePath);